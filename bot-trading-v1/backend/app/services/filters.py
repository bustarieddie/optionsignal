"""Session + news filter orchestration for the live pipeline (rulebook §B.10).

Kept out of the risk engine so risk stays a pure function of the RiskState; these
filters depend on wall-clock/session context and the (optional) news feed.
"""
from __future__ import annotations

from datetime import datetime, time

from app.core import reject_codes as rc
from app.services.news import evaluate_news, news_blocks_trade
from app.services.sessions import SessionWindow, is_tradeable


def _parse_hhmm(value: str) -> time:
    hh, mm = str(value).split(":")
    return time(int(hh), int(mm))


def build_session_windows(symbols: dict, session_defs: dict) -> dict[str, list[SessionWindow]]:
    """From config: per-symbol list of SessionWindow, honoring per-symbol
    open/close avoidance buffers (§16)."""
    out: dict[str, list[SessionWindow]] = {}
    for sym, cfg in symbols.items():
        windows: list[SessionWindow] = []
        for name in cfg.get("sessions", []):
            d = session_defs.get(name)
            if not d:
                continue
            windows.append(SessionWindow(
                name=name,
                start=_parse_hhmm(d["start"]),
                end=_parse_hhmm(d["end"]),
                tz=d["tz"],
                avoid_first_minutes=int(cfg.get("avoid_first_minutes", 0)),
                avoid_last_minutes=int(cfg.get("avoid_last_minutes", 0)),
            ))
        out[sym] = windows
    return out


def check_session(symbol: str, at: datetime, windows_by_symbol: dict, *, enabled: bool) -> str | None:
    if not enabled:
        return None
    windows = windows_by_symbol.get(symbol, [])
    if windows and not is_tradeable(at, windows):
        return rc.REJECT_SESSION
    return None


def check_news(symbol: str, at: datetime, provider, filters: dict) -> str | None:
    if not filters.get("use_news_filter", True):
        return None
    status = evaluate_news(
        provider=provider, symbol=symbol, at=at,
        before_min=filters.get("news_before_min", 30),
        after_min=filters.get("news_after_min", 30),
        major_before_min=filters.get("news_major_before_min", 60),
        major_after_min=filters.get("news_major_after_min", 60),
    )
    if news_blocks_trade(status, use_news_filter=True,
                         fail_safe_block=filters.get("news_fail_safe_block", True)):
        return rc.REJECT_NEWS_WINDOW
    return None
