from app.brokers.mock import MockBroker
from app.schemas.domain import Side
from app.services.management import (
    CloseFull,
    ClosePartial,
    ExitConfig,
    ManagedPosition,
    MoveStop,
    apply_actions,
    on_opposite_structure,
    plan_management,
)


def _mp(**over):
    base = dict(position_id="p1", side=Side.BUY, entry=2400.0, initial_stop=2396.0,
                current_stop=2396.0, size=1.0)
    base.update(over)
    return ManagedPosition(**base)


def test_initial_risk_and_r():
    mp = _mp()
    assert mp.initial_risk == 4.0
    assert mp.r_at(2404.0) == 1.0
    assert mp.r_at(2402.0) == 0.5


# --- partial mode ---
def test_partial_takes_profit_and_moves_breakeven_at_1r():
    mp = _mp()
    cfg = ExitConfig(exit_mode="partial", p1_pct=50, p1_r=1.0, be_r=1.0)
    actions = plan_management(mp, price=2404.0, atr=2.0, cfg=cfg)
    assert any(isinstance(a, ClosePartial) and a.fraction == 0.5 for a in actions)
    assert any(isinstance(a, MoveStop) and a.price == 2400.0 for a in actions)
    assert mp.partial_taken and mp.breakeven_done and mp.current_stop == 2400.0


def test_partial_is_idempotent():
    mp = _mp()
    cfg = ExitConfig(exit_mode="partial")
    plan_management(mp, 2404.0, 2.0, cfg)
    again = plan_management(mp, 2405.0, 2.0, cfg)
    assert again == [] or all(not isinstance(a, ClosePartial) for a in again)


def test_partial_not_triggered_below_1r():
    mp = _mp()
    cfg = ExitConfig(exit_mode="partial")
    assert plan_management(mp, 2402.0, 2.0, cfg) == []


# --- atr trail ---
def test_atr_trail_activates_and_moves_up():
    mp = _mp()
    cfg = ExitConfig(exit_mode="atr_trail", trail_activate_r=1.0, trail_atr_mult=1.5)
    a1 = plan_management(mp, price=2404.0, atr=2.0, cfg=cfg)   # r=1 -> activate, stop 2404-3=2401
    assert any(isinstance(a, MoveStop) and a.price == 2401.0 for a in a1)
    a2 = plan_management(mp, price=2410.0, atr=2.0, cfg=cfg)   # stop -> 2407
    assert any(isinstance(a, MoveStop) and a.price == 2407.0 for a in a2)


def test_atr_trail_never_widens_on_pullback():
    mp = _mp()
    cfg = ExitConfig(exit_mode="atr_trail", trail_activate_r=1.0, trail_atr_mult=1.5)
    plan_management(mp, 2410.0, 2.0, cfg)          # stop 2407
    pull = plan_management(mp, 2405.0, 2.0, cfg)   # would be 2402 < 2407 -> no move
    assert all(not isinstance(a, MoveStop) for a in pull)
    assert mp.current_stop == 2407.0


def test_atr_trail_not_active_below_threshold():
    mp = _mp()
    cfg = ExitConfig(exit_mode="atr_trail", trail_activate_r=1.0)
    assert plan_management(mp, 2402.0, 2.0, cfg) == []


# --- time exit ---
def test_time_exit_when_no_progress():
    mp = _mp(bars_open=48)
    cfg = ExitConfig(exit_mode="fixed_rr", max_trade_bars=48, p1_r=1.0)
    actions = plan_management(mp, price=2402.0, atr=2.0, cfg=cfg)   # peak r 0.5 < 1
    assert any(isinstance(a, CloseFull) and a.reason == "time_exit" for a in actions)


def test_no_time_exit_if_progress_made():
    mp = _mp(bars_open=10)
    cfg = ExitConfig(exit_mode="atr_trail", max_trade_bars=48, p1_r=1.0)
    plan_management(mp, 2405.0, 2.0, cfg)   # reaches >1R, peak_r recorded
    mp.bars_open = 48
    late = plan_management(mp, 2403.0, 2.0, cfg)
    assert all(not (isinstance(a, CloseFull) and a.reason == "time_exit") for a in late)


# --- short side ---
def test_short_r_and_trail():
    mp = _mp(side=Side.SELL, entry=2400.0, initial_stop=2404.0, current_stop=2404.0)
    assert mp.r_at(2396.0) == 1.0
    cfg = ExitConfig(exit_mode="atr_trail", trail_activate_r=1.0, trail_atr_mult=1.5)
    actions = plan_management(mp, price=2396.0, atr=2.0, cfg=cfg)  # stop 2396+3=2399
    assert any(isinstance(a, MoveStop) and a.price == 2399.0 for a in actions)


# --- structure exit + applier ---
def test_structure_exit_closes():
    assert on_opposite_structure(_mp()) == [CloseFull("structure_exit")]


def test_apply_actions_drives_broker():
    b = MockBroker(price=2400)
    r = b.place_market_order("XAUUSD", Side.BUY, 1.0, client_order_id="x")
    mp = _mp(position_id=r.order_id)
    alerts = []
    applied = apply_actions(b, mp, [MoveStop(2400.0, "breakeven")],
                            notifier=lambda e, m: alerts.append((e, m)))
    assert "breakeven" in applied
    assert b.get_open_positions()[0].stop_loss == 2400.0
    assert alerts and alerts[0][0] == "breakeven"
