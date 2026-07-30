from datetime import datetime, timedelta, timezone

from app.core import reject_codes as rc
from app.services.validation import resolve_symbol, validate_signal

MAPPING = {
    "XAUUSD": ["XAUUSD.a", "GOLD"],
    "NAS100": ["USTEC", "US100"],
}


def _payload(**over):
    now = datetime.now(timezone.utc)
    base = {
        "signal_id": "XAUUSD-5-1-BUY",
        "symbol": "XAUUSD",
        "broker_symbol": "XAUUSD",
        "side": "BUY",
        "timeframe": "5",
        "entry_type": "RETEST",
        "entry_price": 2400.0,
        "stop_loss": 2396.0,
        "take_profit": 2408.0,
        "atr_5m": 2.0,
        "risk_reward": 2.0,
        "risk_percent": 0.5,
        "direction_4h": "BULLISH",
        "trend_1h": "BULLISH",
        "pullback_15m": True,
        "trigger_5m": "BULLISH_BOS",
        "bar_time": now.isoformat(),
        "setup_expiry": (now + timedelta(minutes=60)).isoformat(),
    }
    base.update(over)
    return base


def test_valid_signal_parses():
    sig, reason = validate_signal(
        data=_payload(), symbol_mapping=MAPPING, latest_price=2400.1, signal_dev_atr=0.25
    )
    assert reason is None and sig is not None
    assert sig.symbol == "XAUUSD" and sig.side.value == "BUY"


def test_expired_signal_rejected():
    now = datetime.now(timezone.utc)
    sig, reason = validate_signal(
        data=_payload(setup_expiry=(now - timedelta(minutes=1)).isoformat()),
        symbol_mapping=MAPPING, latest_price=2400.0, signal_dev_atr=0.25,
    )
    assert sig is None and reason == rc.REJECT_SIGNAL_EXPIRED


def test_alias_symbol_resolves():
    assert resolve_symbol("GOLD", MAPPING) == "XAUUSD"
    assert resolve_symbol("USTEC", MAPPING) == "NAS100"
    assert resolve_symbol("BTCUSD", MAPPING) is None


def test_unknown_symbol_rejected():
    sig, reason = validate_signal(
        data=_payload(symbol="BTCUSD"), symbol_mapping=MAPPING,
        latest_price=2400.0, signal_dev_atr=0.25,
    )
    assert reason == rc.REJECT_UNKNOWN_SYMBOL


def test_price_deviation_rejected():
    # latest price 2401 vs entry 2400, atr 2, dev limit 0.25*2=0.5 -> 1.0 > 0.5
    sig, reason = validate_signal(
        data=_payload(), symbol_mapping=MAPPING, latest_price=2401.0, signal_dev_atr=0.25
    )
    assert reason == rc.REJECT_SIGNAL_DEVIATION


def test_context_mismatch_rejected():
    sig, reason = validate_signal(
        data=_payload(side="BUY", direction_4h="BEARISH"),
        symbol_mapping=MAPPING, latest_price=2400.0, signal_dev_atr=0.25,
    )
    assert reason == rc.REJECT_1H_TREND_MISMATCH


def test_neutral_4h_rejected():
    sig, reason = validate_signal(
        data=_payload(direction_4h="NEUTRAL"),
        symbol_mapping=MAPPING, latest_price=2400.0, signal_dev_atr=0.25,
    )
    assert reason == rc.REJECT_4H_NEUTRAL


def test_no_trigger_rejected():
    sig, reason = validate_signal(
        data=_payload(trigger_5m="NONE"),
        symbol_mapping=MAPPING, latest_price=2400.0, signal_dev_atr=0.25,
    )
    assert reason == rc.REJECT_NO_5M_TRIGGER
