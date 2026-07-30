"""Signal Validation Engine (rulebook §A.3, §B.10, §18).

Turns a raw (already type-checked) payload dict into a normalized ``Signal`` or a
reject reason. Independent of risk state — it only concerns signal integrity:
expiry, symbol mapping, context consistency, and price deviation vs the latest
broker price. Pure functions; no I/O beyond the injected latest_price.
"""
from __future__ import annotations

from datetime import datetime, timezone

from app.core import reject_codes as rc
from app.schemas.domain import Side, Signal


def _parse_dt(value) -> datetime:
    if isinstance(value, datetime):
        dt = value
    else:
        dt = datetime.fromisoformat(str(value).replace("Z", "+00:00"))
    return dt if dt.tzinfo else dt.replace(tzinfo=timezone.utc)


def resolve_symbol(raw_symbol: str, symbol_mapping: dict[str, list[str]]) -> str | None:
    """Map any broker alias back to the canonical symbol key (§16)."""
    s = raw_symbol.upper()
    for canonical, aliases in symbol_mapping.items():
        if s == canonical.upper() or s in {a.upper() for a in aliases}:
            return canonical
    return None


def context_consistent(side: Side, data: dict) -> str | None:
    """Reject if the payload's MTF context contradicts the side (§B.1–B.4)."""
    d4 = str(data.get("direction_4h", "")).upper()
    t1 = str(data.get("trend_1h", "")).upper()
    pb = bool(data.get("pullback_15m", False))
    trig = str(data.get("trigger_5m", "")).upper()

    want = "BULLISH" if side is Side.BUY else "BEARISH"
    if d4 == "NEUTRAL" or d4 != want:
        return rc.REJECT_4H_NEUTRAL if d4 == "NEUTRAL" else rc.REJECT_1H_TREND_MISMATCH
    if t1 != want:
        return rc.REJECT_1H_TREND_MISMATCH
    if not pb:
        return rc.REJECT_NO_PULLBACK
    if not trig or "BOS" not in trig and "CHOCH" not in trig:
        return rc.REJECT_NO_5M_TRIGGER
    return None


def validate_signal(
    *,
    data: dict,
    symbol_mapping: dict[str, list[str]],
    latest_price: float | None,
    signal_dev_atr: float,
    now: datetime | None = None,
) -> tuple[Signal | None, str | None]:
    """Return (Signal, None) on success or (None, REJECT_*)."""
    now = now or datetime.now(timezone.utc)

    # Side.
    try:
        side = Side(str(data["side"]).upper())
    except (KeyError, ValueError):
        return None, rc.REJECT_MALFORMED

    # Symbol mapping.
    canonical = resolve_symbol(str(data.get("symbol", "")), symbol_mapping)
    if canonical is None:
        return None, rc.REJECT_UNKNOWN_SYMBOL

    # Expiry / staleness.
    try:
        setup_expiry = _parse_dt(data["setup_expiry"])
        bar_time = _parse_dt(data["bar_time"])
    except Exception:
        return None, rc.REJECT_MALFORMED
    if now > setup_expiry:
        return None, rc.REJECT_SIGNAL_EXPIRED

    # MTF context consistency.
    ctx = context_consistent(side, data)
    if ctx:
        return None, ctx

    # Numeric fields.
    try:
        entry = float(data["entry_price"])
        sl = float(data["stop_loss"])
        tp = float(data["take_profit"])
        atr5 = float(data["atr_5m"])
    except (KeyError, ValueError, TypeError):
        return None, rc.REJECT_MALFORMED

    # Price-deviation guard vs latest broker price (F-DEV).
    if latest_price is not None and atr5 > 0:
        if abs(latest_price - entry) > signal_dev_atr * atr5:
            return None, rc.REJECT_SIGNAL_DEVIATION

    sig = Signal(
        signal_id=str(data["signal_id"]),
        symbol=canonical,
        broker_symbol=str(data.get("broker_symbol") or canonical),
        side=side,
        timeframe=str(data.get("timeframe", "5")),
        entry_type=str(data.get("entry_type", "RETEST")).upper(),
        entry_price=entry,
        stop_loss=sl,
        take_profit=tp,
        atr_5m=atr5,
        reward_risk=float(data.get("risk_reward", 2.0)),
        risk_percent=float(data.get("risk_percent", 0.5)),
        direction_4h=str(data.get("direction_4h", "")).upper(),
        trend_1h=str(data.get("trend_1h", "")).upper(),
        pullback_15m=bool(data.get("pullback_15m", False)),
        trigger_5m=str(data.get("trigger_5m", "")).upper(),
        bar_time=bar_time,
        setup_expiry=setup_expiry,
        spread=float(data["spread"]) if data.get("spread") is not None else None,
        raw=data,
    )
    return sig, None
