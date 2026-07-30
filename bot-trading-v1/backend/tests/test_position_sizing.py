from app.core import reject_codes as rc
from app.services.position_sizing import calculate_position_size


def test_basic_gold_sizing(xauusd_spec):
    # equity 10_000, risk 0.5% => $50 risk; stop 4.0 price => 400 ticks * $1 = $400/lot
    r = calculate_position_size(
        equity=10_000, risk_percent=0.5, risk_hard_max_percent=1.0,
        stop_distance=4.0, spec=xauusd_spec,
    )
    assert r.ok
    assert r.loss_per_unit == 400.0
    # raw 0.125 -> floored to 0.12 lot step
    assert r.lots == 0.12
    assert round(r.open_risk_percent, 4) == 0.48  # 0.12 * 400 / 10000 * 100


def test_risk_clamped_to_hard_max(xauusd_spec):
    # ask for 5% but hard max 1% -> sized as if 1%
    big = calculate_position_size(
        equity=10_000, risk_percent=5.0, risk_hard_max_percent=1.0,
        stop_distance=4.0, spec=xauusd_spec,
    )
    at_max = calculate_position_size(
        equity=10_000, risk_percent=1.0, risk_hard_max_percent=1.0,
        stop_distance=4.0, spec=xauusd_spec,
    )
    assert big.lots == at_max.lots


def test_rounds_down_never_up(xauusd_spec):
    r = calculate_position_size(
        equity=10_000, risk_percent=0.5, risk_hard_max_percent=1.0,
        stop_distance=4.0, spec=xauusd_spec,
    )
    # actual risk must never exceed requested risk after rounding
    assert r.open_risk_percent <= 0.5


def test_too_small_rejected(xauusd_spec):
    # tiny equity -> below min lot
    r = calculate_position_size(
        equity=10, risk_percent=0.5, risk_hard_max_percent=1.0,
        stop_distance=50.0, spec=xauusd_spec,
    )
    assert not r.ok
    assert r.reason == rc.REJECT_POSITION_TOO_SMALL


def test_unreliable_inputs_rejected(xauusd_spec):
    bad = xauusd_spec.__class__(**{**xauusd_spec.__dict__, "tick_value": 0.0})
    r = calculate_position_size(
        equity=10_000, risk_percent=0.5, risk_hard_max_percent=1.0,
        stop_distance=4.0, spec=bad,
    )
    assert not r.ok
    assert r.reason == rc.REJECT_SIZING_UNRELIABLE


def test_zero_stop_rejected(xauusd_spec):
    r = calculate_position_size(
        equity=10_000, risk_percent=0.5, risk_hard_max_percent=1.0,
        stop_distance=0.0, spec=xauusd_spec,
    )
    assert not r.ok
    assert r.reason == rc.REJECT_STOP_TOO_TIGHT


def test_fx_conversion_scales_risk(xauusd_spec):
    base = calculate_position_size(
        equity=10_000, risk_percent=0.5, risk_hard_max_percent=1.0,
        stop_distance=4.0, spec=xauusd_spec, fx_to_account=1.0,
    )
    doubled = calculate_position_size(
        equity=10_000, risk_percent=0.5, risk_hard_max_percent=1.0,
        stop_distance=4.0, spec=xauusd_spec, fx_to_account=2.0,
    )
    # If each unit costs 2x in account ccy, lots roughly halve.
    assert doubled.lots < base.lots
