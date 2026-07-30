"""BOT TRADING v1.0 — FastAPI application entrypoint.

Boots in PAPER mode with LIVE trading disabled. Wires one authoritative Runtime
onto app.state and mounts the webhook / admin / read routers.

    uvicorn app.main:app --reload
"""
from __future__ import annotations

from contextlib import asynccontextmanager

from fastapi import FastAPI

from app.api.routes import admin, read, webhook
from app.core.logging import get_logger, log_event
from app.core.runtime import build_runtime

log = get_logger("botv1")

SAFETY_BANNER = (
    "Algorithmic trading involves substantial financial risk. Backtested or "
    "simulated performance does not guarantee future results. The system must be "
    "independently tested before real-money deployment."
)


@asynccontextmanager
async def lifespan(app: FastAPI):
    rt = build_runtime()
    app.state.runtime = rt
    # Best-effort DB init (dev). Safe to skip if DB not configured.
    try:
        from app.db.session import init_db
        init_db()
    except Exception as e:  # pragma: no cover
        log_event(log, "db init skipped", error=str(e))
    log_event(log, "startup", environment=rt.settings.environment,
              live_enabled=rt.live_enabled(), state=rt.sm.state.value,
              auth_mode=rt.settings.auth_mode(), safety=SAFETY_BANNER)
    yield
    log_event(log, "shutdown")


app = FastAPI(title="BOT TRADING v1.0", version="1.0.0", lifespan=lifespan)
app.include_router(webhook.router)
app.include_router(admin.router)
app.include_router(read.router)


@app.get("/")
async def root():
    return {
        "name": "BOT TRADING v1.0",
        "safety": SAFETY_BANNER,
        "docs": "/docs",
        "note": "Paper mode by default. Live trading disabled by default.",
    }
