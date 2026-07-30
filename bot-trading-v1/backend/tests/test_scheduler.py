from datetime import datetime, timezone
from types import SimpleNamespace

from app.brokers.paper import PaperBroker
from app.core.states import StateMachine, SystemState
from app.schemas.domain import Position, RiskState, Side
from app.services.management import ManagedPosition
from app.services.notifications import NotificationDispatcher, RecordingChannel
from app.services.scheduler import (
    build_daily_summary,
    current_day_key,
    daily_reset,
    run_management_cycle,
)


def _rt(broker, managed=None, strategy=None, state=SystemState.READY):
    ch = RecordingChannel()
    return SimpleNamespace(
        broker=broker,
        managed=managed or {},
        risk_state=RiskState(equity=10_000.0),
        settings=SimpleNamespace(strategy=strategy or {}, system={"timezone": "Asia/Kuching"}),
        notifier=NotificationDispatcher([ch]),
        sm=StateMachine(state),
    )


def test_cycle_moves_trailing_stop():
    b = PaperBroker(prices={"XAUUSD": 2410.0}, spread={"XAUUSD": 0.0}, tick_size={"XAUUSD": 0.01})
    b.connect()
    r = b.place_market_order("XAUUSD", Side.BUY, 1.0, client_order_id="x")
    b.set_protection(r.order_id, stop_loss=2396.0, take_profit=2408.0)
    mp = ManagedPosition(position_id=r.order_id, side=Side.BUY, entry=2400.0,
                         initial_stop=2396.0, current_stop=2396.0, size=1.0, atr=2.0)
    rt = _rt(b, managed={r.order_id: mp}, strategy={"exit_mode": "atr_trail",
                                                    "trail_activate_r": 1.0, "trail_atr_mult": 1.5})
    res = run_management_cycle(rt)
    # price 2410 -> r=2.5, trail stop to 2410-3=2407
    assert mp.current_stop == 2407.0
    assert res[0]["applied"] == ["atr_trail"]


def test_cycle_cleans_up_broker_closed_position():
    b = PaperBroker(prices={"XAUUSD": 2410.0})
    b.connect()
    # tracked position that the broker doesn't have (already closed)
    mp = ManagedPosition(position_id="ghost", side=Side.BUY, entry=2400,
                         initial_stop=2396, current_stop=2396, size=1.0)
    rt = _rt(b, managed={"ghost": mp})
    rt.risk_state.open_positions.append(Position("XAUUSD", Side.BUY, 1, 2400, 2396, 0.4))
    res = run_management_cycle(rt)
    assert res[0]["closed"] is True
    assert "ghost" not in rt.managed


def test_daily_reset_clears_counters_and_lifts_lock():
    b = PaperBroker()
    rt = _rt(b, state=SystemState.RISK_LOCKED)
    rt.risk_state.trades_today = 3
    rt.risk_state.losing_trades_today = 2
    rt.risk_state.realized_pnl_today = -150.0
    rt.risk_state.seen_candles.add(("XAUUSD", "5", datetime.now(timezone.utc)))
    daily_reset(rt)
    assert rt.risk_state.trades_today == 0
    assert rt.risk_state.losing_trades_today == 0
    assert rt.risk_state.realized_pnl_today == 0.0
    assert rt.risk_state.seen_candles == set()
    assert rt.sm.state is SystemState.READY   # RISK_LOCK lifted


def test_daily_reset_no_lock_stays():
    b = PaperBroker()
    rt = _rt(b, state=SystemState.READY)
    daily_reset(rt)
    assert rt.sm.state is SystemState.READY


def test_daily_summary_notifies():
    b = PaperBroker()
    rt = _rt(b)
    rt.risk_state.trades_today = 2
    msg = build_daily_summary(rt)
    assert "Daily summary" in msg
    assert rt.notifier.recent()[0]["event"] == "daily_summary"


def test_current_day_key_is_tz_aware():
    # a UTC instant near midnight maps to the next day in Asia/Kuching (UTC+8)
    dt = datetime(2026, 7, 30, 20, 0, tzinfo=timezone.utc)  # 04:00 next day in Kuching
    assert current_day_key("Asia/Kuching", dt) == "2026-07-31"
    assert current_day_key("UTC", dt) == "2026-07-30"
