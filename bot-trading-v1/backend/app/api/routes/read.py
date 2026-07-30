"""Read endpoints for the dashboard (rulebook §19, §25)."""
from __future__ import annotations

from fastapi import APIRouter, Request

router = APIRouter()


@router.get("/health")
async def health(request: Request):
    rt = request.app.state.runtime
    return {
        "status": "ok",
        "state": rt.sm.state.value,
        "environment": rt.settings.environment,
        "live_enabled": rt.live_enabled(),
        "auth_mode": rt.settings.auth_mode(),
        "broker": "connected" if rt.broker.is_connected() else "disconnected",
        "news_filter": "unavailable",   # honest default until a real feed is wired
    }


@router.get("/positions")
async def positions(request: Request):
    rt = request.app.state.runtime
    return [
        {"symbol": p.symbol, "side": p.side.value, "size": p.size,
         "entry": p.entry_price, "stop": p.stop_loss,
         "open_risk_percent": p.open_risk_percent}
        for p in rt.risk_state.open_positions
    ]


@router.get("/signals")
async def signals(request: Request):
    # Placeholder read model — production reads signal_events from the DB.
    return {"note": "reads signal_events from DB in production", "recent": []}


@router.get("/trades")
async def trades(request: Request):
    return {"note": "reads trades from DB in production", "recent": []}
