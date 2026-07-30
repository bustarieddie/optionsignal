"""Admin controls (rulebook §19): kill-switch, pause, resume, close, risk-status.

Bearer-token protected with failure lockout. Every action is audited.
"""
from __future__ import annotations

import uuid

from fastapi import APIRouter, Header, HTTPException, Request

from app.core.logging import get_logger, log_event
from app.core.states import SystemState

router = APIRouter(prefix="/admin")
log = get_logger("botv1.admin")


def _require_admin(request: Request, authorization: str | None):
    rt = request.app.state.runtime
    token = (authorization or "").replace("Bearer ", "").strip()
    ok, why = rt.admin.check(token)
    if not ok:
        raise HTTPException(status_code=401, detail=why)
    return rt


def _audit(rt, action: str, **detail):
    corr = str(uuid.uuid4())
    log_event(log, f"admin:{action}", correlation_id=corr, **detail)
    return corr


@router.post("/kill-switch")
async def kill_switch(request: Request, authorization: str | None = Header(default=None)):
    rt = _require_admin(request, authorization)
    rt.sm.transition(SystemState.EMERGENCY_STOP, "admin kill switch")
    corr = _audit(rt, "kill_switch")
    if rt.notifier:
        rt.notifier.notify("kill_switch", "KILL SWITCH activated — EMERGENCY_STOP")
    return {"state": rt.sm.state.value, "correlation_id": corr}


@router.post("/pause")
async def pause(request: Request, authorization: str | None = Header(default=None),
                symbol: str | None = None):
    rt = _require_admin(request, authorization)
    if symbol:
        rt.paused_symbols.add(symbol)
    else:
        rt.sm.transition(SystemState.PAUSED, "admin pause")
    if rt.notifier:
        rt.notifier.notify("paused", f"paused {symbol or 'ALL new entries'}")
    return {"state": rt.sm.state.value, "paused_symbols": sorted(rt.paused_symbols),
            "correlation_id": _audit(rt, "pause", symbol=symbol)}


@router.post("/resume")
async def resume(request: Request, authorization: str | None = Header(default=None),
                 symbol: str | None = None):
    rt = _require_admin(request, authorization)
    if symbol:
        rt.paused_symbols.discard(symbol)
    else:
        rt.sm.transition(SystemState.READY, "admin resume")
    return {"state": rt.sm.state.value, "paused_symbols": sorted(rt.paused_symbols),
            "correlation_id": _audit(rt, "resume", symbol=symbol)}


@router.post("/close-position")
async def close_position(request: Request, position_id: str,
                         authorization: str | None = Header(default=None)):
    rt = _require_admin(request, authorization)
    res = rt.broker.close_position(position_id)
    rt.risk_state.open_positions = [
        p for p in rt.risk_state.open_positions if getattr(p, "position_id", None) != position_id
    ]
    return {"ok": res.ok, "reason": res.reason,
            "correlation_id": _audit(rt, "close_position", position_id=position_id)}


@router.post("/manage-tick")
async def manage_tick(request: Request, symbol: str, price: float, atr: float = 0.0,
                      advance_bar: bool = False,
                      authorization: str | None = Header(default=None)):
    """Run the position-management engine (rulebook §9) for a symbol's open
    position at the given price. Drive this from a bar-close / price loop or a
    scheduled job. Returns the actions applied and any closures."""
    rt = _require_admin(request, authorization)
    from app.services.management import apply_actions, exit_config_from, plan_management

    cfg = exit_config_from(rt.settings.strategy)
    notify = (lambda e, m: rt.notifier.notify(e, m)) if rt.notifier else None
    out = []
    for pid, mp in list(rt.managed.items()):
        pos = next((p for p in rt.broker.get_open_positions() if p.position_id == pid), None)
        if pos is None or pos.symbol != symbol:
            continue
        if advance_bar:
            mp.bars_open += 1
        actions = plan_management(mp, price, atr, cfg)
        applied = apply_actions(rt.broker, mp, actions, notifier=notify)
        # Sync local state if the position fully closed.
        still_open = any(p.position_id == pid for p in rt.broker.get_open_positions())
        if not still_open:
            rt.managed.pop(pid, None)
            rt.risk_state.open_positions = [
                p for p in rt.risk_state.open_positions if p.symbol != symbol
            ]
        out.append({"position_id": pid, "applied": applied, "closed": not still_open})
    return {"symbol": symbol, "results": out,
            "correlation_id": _audit(rt, "manage_tick", symbol=symbol, price=price)}


@router.get("/risk-status")
async def risk_status(request: Request, authorization: str | None = Header(default=None)):
    rt = _require_admin(request, authorization)
    st = rt.risk_state
    return {
        "state": rt.sm.state.value,
        "environment": rt.settings.environment,
        "live_enabled": rt.live_enabled(),
        "equity": st.equity,
        "open_risk_percent": st.open_risk_percent(),
        "trades_today": st.trades_today,
        "losing_trades_today": st.losing_trades_today,
        "consecutive_losses": st.consecutive_losses,
        "daily_pnl": st.realized_pnl_today,
        "weekly_pnl": st.realized_pnl_week,
        "open_positions": len(st.open_positions),
        "paused_symbols": sorted(rt.paused_symbols),
    }
