"""POST /webhook/tradingview/{url_token} — the receiver + full pipeline (§19)."""
from __future__ import annotations

import uuid

from fastapi import APIRouter, Header, Request
from fastapi.responses import JSONResponse

from app.core import reject_codes as rc
from app.core.event_store import SignalRecord, TradeRecord
from app.core.idempotency import signal_key
from app.core.logging import get_logger, log_event
from app.core.security import authenticate_webhook
from app.schemas.webhook import TradingViewWebhook
from app.services import risk_engine
from app.services.executor import execute
from app.services.filters import check_news, check_session
from app.services.validation import validate_signal

router = APIRouter()
log = get_logger("botv1.webhook")


@router.post("/webhook/tradingview/{url_token}")
async def tradingview(url_token: str, request: Request,
                      x_signature: str | None = Header(default=None)):
    rt = request.app.state.runtime
    corr = str(uuid.uuid4())
    raw = await request.body()

    # 1. Parse JSON.
    try:
        data = await request.json()
    except Exception:
        return _reject(400, rc.REJECT_MALFORMED, corr)

    # 2. Authenticate (secret + url token, or HMAC).
    ok, why = authenticate_webhook(
        cfg=rt.auth, url_token=url_token, raw_body=raw,
        payload_secret=data.get("secret"), signature_header=x_signature,
        client_ip=request.client.host if request.client else None,
    )
    if not ok:
        log_event(log, "webhook auth failed", correlation_id=corr, reason=why)
        return _reject(401, rc.REJECT_BAD_SIGNATURE, corr)

    # 3. Schema validation.
    try:
        model = TradingViewWebhook(**data)
    except Exception as e:  # pydantic ValidationError
        return _reject(422, rc.REJECT_MALFORMED, corr, detail=str(e))

    # 4. Idempotency (dedup by signal_id).
    if not rt.dedup.claim(signal_key(model.signal_id), rt.settings.signal_dedup_ttl):
        return JSONResponse(status_code=200,
                            content={"status": "duplicate", "reason": rc.REJECT_DUPLICATE_SIGNAL,
                                     "correlation_id": corr})

    # 5. Signal validation (expiry, symbol, deviation, context).
    latest = _safe_price(rt, model.broker_symbol or model.symbol)
    sig, reason = validate_signal(
        data=model.to_core_dict(),
        symbol_mapping=rt.settings.symbol_mapping,
        latest_price=latest,
        signal_dev_atr=rt.settings.filters.get("signal_dev_atr", 0.25),
    )
    if reason:
        rt.events.record_signal(SignalRecord(
            signal_id=model.signal_id, symbol=model.symbol, side=model.side,
            status="rejected", reason=reason, correlation_id=corr,
            environment=rt.settings.environment))
        return _reject(200, reason, corr)

    # 6. Symbol paused? / entries allowed?
    if sig.symbol in rt.paused_symbols or not rt.sm.entries_allowed():
        rt.events.record_signal(SignalRecord(
            signal_id=sig.signal_id, symbol=sig.symbol, side=sig.side.value,
            status="rejected", reason="REJECT_PAUSED", correlation_id=corr,
            environment=rt.settings.environment))
        return _reject(200, "REJECT_PAUSED", corr)

    # 6b. Session + news filters (rulebook §B.10, F-SESS / F-NEWS).
    filt = (
        check_session(sig.symbol, sig.bar_time, rt.session_windows,
                      enabled=rt.settings.filters.get("use_session_filter", True))
        or check_news(sig.symbol, sig.bar_time, rt.news_provider, rt.settings.filters)
    )
    if filt:
        rt.events.record_signal(SignalRecord(
            signal_id=sig.signal_id, symbol=sig.symbol, side=sig.side.value,
            status="rejected", reason=filt, correlation_id=corr,
            environment=rt.settings.environment))
        return _reject(200, filt, corr)

    spec = rt.specs.get(sig.symbol)
    if spec is None:
        return _reject(200, rc.REJECT_UNKNOWN_SYMBOL, corr)

    # 7. Risk engine.
    decision = risk_engine.evaluate(
        signal=sig, spec=spec, limits=rt.limits, state=rt.risk_state,
        live_enabled=rt.live_enabled(), environment=rt.settings.environment,
    )
    if not decision.approved:
        log_event(log, "risk rejected", correlation_id=corr, reason=decision.reason)
        rt.events.record_signal(SignalRecord(
            signal_id=sig.signal_id, symbol=sig.symbol, side=sig.side.value,
            status="rejected", reason=decision.reason, correlation_id=corr,
            environment=rt.settings.environment))
        _notify(rt, "risk_limit" if _is_risk_limit(decision.reason) else "rejected_signal",
                f"{sig.symbol} {sig.side.value} rejected: {decision.reason}")
        return _reject(200, decision.reason, corr)

    # 8. Execute (paper by default) with emergency protection policy.
    result = execute(decision.order_intent, rt.broker,
                     notifier=lambda m: _notify(rt, "order_rejected", m))
    if not result.ok and result.paused_symbol:
        rt.paused_symbols.add(result.paused_symbol)

    # 9. Update risk state on a successful, protected entry.
    if result.ok and result.protected:
        from app.schemas.domain import Position
        rt.risk_state.trades_today += 1
        rt.risk_state.seen_candles.add((sig.symbol, sig.timeframe, sig.bar_time))
        rt.risk_state.open_positions.append(Position(
            symbol=sig.symbol, side=sig.side, size=decision.order_intent.lots,
            entry_price=sig.entry_price, stop_loss=sig.stop_loss,
            open_risk_percent=decision.order_intent.open_risk_percent,
            correlation_group=spec.correlation_group,
        ))
        rt.events.record_trade(TradeRecord(
            symbol=sig.symbol, side=sig.side.value, size=decision.order_intent.lots,
            entry_price=sig.entry_price, stop_loss=sig.stop_loss, take_profit=sig.take_profit,
            position_id=result.position_id,
            open_risk_percent=decision.order_intent.open_risk_percent,
            environment=rt.settings.environment, status="open"))
        _notify(rt, "order_placed",
                f"{sig.symbol} {sig.side.value} {decision.order_intent.lots} lots @ "
                f"{sig.entry_price} SL {sig.stop_loss} TP {sig.take_profit}")

    rt.events.record_signal(SignalRecord(
        signal_id=sig.signal_id, symbol=sig.symbol, side=sig.side.value,
        status="accepted" if result.ok else "execution_failed",
        reason=None if result.ok else result.reason, correlation_id=corr,
        environment=rt.settings.environment))
    log_event(log, "signal processed", correlation_id=corr, signal_id=sig.signal_id,
              approved=decision.approved, executed=result.ok, protected=result.protected)
    return JSONResponse(status_code=200, content={
        "status": "accepted" if result.ok else "execution_failed",
        "protected": result.protected,
        "position_id": result.position_id,
        "lots": decision.order_intent.lots,
        "correlation_id": corr,
    })


_RISK_LIMIT_CODES = {
    rc.REJECT_MAX_TRADES, rc.REJECT_MAX_DAILY_LOSSES, rc.REJECT_MAX_CONSEC_LOSSES,
    rc.REJECT_DAILY_LOSS_LIMIT, rc.REJECT_WEEKLY_LOSS_LIMIT, rc.REJECT_MAX_OPEN_RISK,
    rc.REJECT_CORRELATED_EXPOSURE,
}


def _is_risk_limit(reason: str | None) -> bool:
    return reason in _RISK_LIMIT_CODES


def _notify(rt, event: str, message: str) -> None:
    if rt.notifier is not None:
        try:
            rt.notifier.notify(event, message)
        except Exception:
            pass


def _safe_price(rt, symbol: str) -> float | None:
    try:
        p = rt.broker.get_latest_price(symbol)
        return p if p and p > 0 else None
    except Exception:
        return None


def _reject(code: int, reason: str, corr: str, detail: str | None = None):
    body = {"status": "rejected", "reason": reason, "correlation_id": corr}
    if detail:
        body["detail"] = detail
    return JSONResponse(status_code=code, content=body)
