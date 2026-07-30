"""Background scheduling helpers (rulebook §9 auto-management, §11 daily reset,
§24 daily summary).

The functions here are synchronous and testable. ``management_loop`` wraps
``run_management_cycle`` in an async timer for the FastAPI lifespan; it is opt-in
(``SCHEDULER_ENABLED=true``) so tests and simple deployments aren't forced to run
a background task.
"""
from __future__ import annotations

from datetime import datetime
from zoneinfo import ZoneInfo

from app.core.states import SystemState
from app.services.management import apply_actions, exit_config_from, plan_management


def _notify(rt):
    return (lambda e, m: rt.notifier.notify(e, m)) if rt.notifier else None


def _cleanup_closed(rt, pid: str, symbol: str) -> None:
    rt.managed.pop(pid, None)
    rt.risk_state.open_positions = [
        p for p in rt.risk_state.open_positions if p.symbol != symbol
    ]


def run_management_cycle(rt, *, advance_bar: bool = False) -> list[dict]:
    """Fetch the latest price for each tracked position and run the management
    planner. Cleans up positions the broker already closed (SL/TP hit)."""
    cfg = exit_config_from(rt.settings.strategy)
    notify = _notify(rt)
    open_by_id = {p.position_id: p for p in rt.broker.get_open_positions()}
    results: list[dict] = []

    for pid, mp in list(rt.managed.items()):
        pos = open_by_id.get(pid)
        if pos is None:
            # Broker already closed it (protective SL/TP or manual). Sync + skip.
            _cleanup_closed(rt, pid, _symbol_of(rt, pid))
            results.append({"position_id": pid, "applied": [], "closed": True, "note": "closed_at_broker"})
            continue
        try:
            price = rt.broker.get_latest_price(pos.symbol)
        except Exception:
            continue
        if not price or price <= 0:
            continue
        if advance_bar:
            mp.bars_open += 1
        actions = plan_management(mp, price, mp.atr, cfg)
        applied = apply_actions(rt.broker, mp, actions, notifier=notify)
        still_open = any(p.position_id == pid for p in rt.broker.get_open_positions())
        if not still_open:
            _cleanup_closed(rt, pid, pos.symbol)
        results.append({"position_id": pid, "symbol": pos.symbol,
                        "applied": applied, "closed": not still_open})
    return results


def _symbol_of(rt, pid: str) -> str:
    mp = rt.managed.get(pid)
    return getattr(mp, "symbol", "") if mp else ""


def build_daily_summary(rt) -> str:
    st = rt.risk_state
    msg = (f"Daily summary — trades {st.trades_today}, losing {st.losing_trades_today}, "
           f"consec_losses {st.consecutive_losses}, day P/L {st.realized_pnl_today:.2f}, "
           f"open {len(st.open_positions)}, equity {st.equity:.2f}, state {rt.sm.state.value}")
    if rt.notifier:
        rt.notifier.notify("daily_summary", msg)
    return msg


def daily_reset(rt) -> None:
    """Reset daily counters (rulebook §11) and lift a daily RISK_LOCK. Timezone
    boundary is decided by the caller; this just performs the reset."""
    st = rt.risk_state
    st.trades_today = 0
    st.losing_trades_today = 0
    st.realized_pnl_today = 0.0
    st.seen_candles.clear()
    if rt.sm.state is SystemState.RISK_LOCKED:
        rt.sm.transition(SystemState.READY, "daily_reset")
        if rt.notifier:
            rt.notifier.notify("risk_limit", "daily reset — RISK_LOCK lifted, trading resumes")


def current_day_key(tz: str, now: datetime | None = None) -> str:
    now = now or datetime.now(ZoneInfo(tz))
    if now.tzinfo is None:
        now = now.replace(tzinfo=ZoneInfo(tz))
    return now.astimezone(ZoneInfo(tz)).strftime("%Y-%m-%d")


async def management_loop(app, interval_seconds: int = 15) -> None:  # pragma: no cover - async timer
    """Opt-in async loop for the FastAPI lifespan. Runs the management cycle on a
    timer and performs a daily reset + summary when the tz day rolls over."""
    import asyncio

    rt = app.state.runtime
    tz = rt.settings.system.get("timezone", "Asia/Kuching")
    last_day = current_day_key(tz)
    try:
        while True:
            await asyncio.sleep(interval_seconds)
            try:
                run_management_cycle(rt)
                today = current_day_key(tz)
                if today != last_day:
                    build_daily_summary(rt)
                    daily_reset(rt)
                    last_day = today
            except Exception:
                pass
    except asyncio.CancelledError:
        return
