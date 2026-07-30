from app.core import reject_codes as rc
from app.schemas.domain import Position, Side
from app.services import risk_engine
from tests.conftest import make_signal


def _eval(sig, spec, limits, state, **kw):
    return risk_engine.evaluate(
        signal=sig, spec=spec, limits=limits, state=state,
        live_enabled=False, environment="paper", **kw,
    )


def test_happy_path_approves(xauusd_spec, limits, state):
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert d.approved
    assert d.order_intent is not None
    assert d.order_intent.lots > 0


def test_max_trades_per_day(xauusd_spec, limits, state):
    state.trades_today = limits.max_trades_per_day
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert not d.approved and d.reason == rc.REJECT_MAX_TRADES


def test_max_daily_losses(xauusd_spec, limits, state):
    state.losing_trades_today = limits.max_losing_trades_per_day
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_MAX_DAILY_LOSSES


def test_consecutive_losses(xauusd_spec, limits, state):
    state.consecutive_losses = limits.max_consecutive_losses
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_MAX_CONSEC_LOSSES


def test_daily_loss_limit(xauusd_spec, limits, state):
    state.realized_pnl_today = -0.021 * state.equity  # -2.1% > 2% limit
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_DAILY_LOSS_LIMIT


def test_weekly_loss_limit(xauusd_spec, limits, state):
    state.realized_pnl_week = -0.051 * state.equity
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_WEEKLY_LOSS_LIMIT


def test_one_trade_per_symbol(xauusd_spec, limits, state):
    state.open_positions.append(
        Position("XAUUSD", Side.BUY, 0.1, 2400, 2396, 0.4)
    )
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_SYMBOL_ALREADY_OPEN


def test_one_signal_per_candle(xauusd_spec, limits, state):
    sig = make_signal()
    state.seen_candles.add((sig.symbol, sig.timeframe, sig.bar_time))
    d = _eval(sig, xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_DUPLICATE_CANDLE


def test_stop_too_wide(xauusd_spec, limits, state):
    # stop distance 10 with atr 2 -> 10 > 3*2
    sig = make_signal(entry=2400, stop=2390, atr5=2.0)
    d = _eval(sig, xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_STOP_TOO_WIDE


def test_stop_too_tight(xauusd_spec, limits, state):
    # distance 0.2 < min_stop_distance 0.5
    sig = make_signal(entry=2400.0, stop=2399.8, atr5=2.0)
    d = _eval(sig, xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_STOP_TOO_TIGHT


def test_spread_high(xauusd_spec, limits, state):
    sig = make_signal(spread=1.0)  # > max_spread 0.35
    d = _eval(sig, xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_SPREAD_HIGH


def test_max_open_risk(xauusd_spec, limits, state):
    # already 1.4% open; new ~0.48% would exceed 1.5% cap
    state.open_positions.append(Position("SPX500", Side.BUY, 1, 5000, 4990, 1.4))
    d = _eval(make_signal(), xauusd_spec, limits, state)
    assert d.reason == rc.REJECT_MAX_OPEN_RISK


def test_correlated_exposure(nas_spec, limits, state):
    # existing index group risk 0.8%; +~0.5% > 1.0% group cap
    state.open_positions.append(
        Position("US30", Side.BUY, 1, 40000, 39900, 0.8, correlation_group="indices")
    )
    sig = make_signal(symbol="NAS100", entry=20000, stop=19980, atr5=10.0, spread=1.0)
    d = risk_engine.evaluate(
        signal=sig, spec=nas_spec, limits=limits, state=state,
        live_enabled=False, environment="paper",
    )
    assert d.reason == rc.REJECT_CORRELATED_EXPOSURE


def test_live_disabled(xauusd_spec, limits, state):
    d = risk_engine.evaluate(
        signal=make_signal(), spec=xauusd_spec, limits=limits, state=state,
        live_enabled=False, environment="live",
    )
    assert d.reason == rc.REJECT_LIVE_TRADING_DISABLED
