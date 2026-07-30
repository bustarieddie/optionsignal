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

    # Opt-in background management/daily-reset loop (SCHEDULER_ENABLED=true).
    task = None
    if rt.settings.scheduler_enabled:  # pragma: no cover - background task
        import asyncio

        from app.services.scheduler import management_loop
        task = asyncio.create_task(management_loop(app, rt.settings.scheduler_interval))
        log_event(log, "scheduler started", interval=rt.settings.scheduler_interval)

    log_event(log, "startup", environment=rt.settings.environment,
              live_enabled=rt.live_enabled(), state=rt.sm.state.value,
              auth_mode=rt.settings.auth_mode(), scheduler=rt.settings.scheduler_enabled,
              safety=SAFETY_BANNER)
    yield
    if task is not None:  # pragma: no cover
        task.cancel()
    log_event(log, "shutdown")


app = FastAPI(title="BOT TRADING v1.0", version="1.0.0", lifespan=lifespan)
app.include_router(webhook.router)
app.include_router(admin.router)
app.include_router(read.router)

# Phase 5 — serve the static ops dashboard at /dashboard (read endpoints are same-origin).
try:
    from pathlib import Path

    from fastapi.staticfiles import StaticFiles

    _dash = Path(__file__).resolve().parents[2] / "dashboard"
    if _dash.exists():
        app.mount("/dashboard", StaticFiles(directory=str(_dash), html=True), name="dashboard")
except Exception as e:  # pragma: no cover
    log_event(log, "dashboard mount skipped", error=str(e))


@app.get("/")
async def root():
    return {
        "name": "BOT TRADING v1.0",
        "safety": SAFETY_BANNER,
        "docs": "/docs",
        "note": "Paper mode by default. Live trading disabled by default.",
    }
