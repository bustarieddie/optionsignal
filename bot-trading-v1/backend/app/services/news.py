"""News filter interface (rulebook §14, F-NEWS).

CRITICAL HONESTY REQUIREMENT: TradingView/Pine cannot reliably access a live
economic calendar, and this repository bundles no paid feed. Therefore the news
filter is an INTERFACE with an explicit ``unavailable`` status. We never pretend
news protection is active when it is not.

Wire a real provider by implementing ``NewsProvider`` and passing it in. Until
then the default provider reports ``unavailable`` and the configured fail policy
decides whether to block (fail-safe, default) or allow-with-warning.
"""
from __future__ import annotations

import enum
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Protocol


class NewsStatus(str, enum.Enum):
    CLEAR = "clear"              # no blocking event near this time
    BLOCKED = "blocked"         # inside a high-impact blackout window
    UNAVAILABLE = "unavailable"  # no verified feed connected — cannot assert safety


@dataclass(frozen=True)
class NewsEvent:
    name: str
    at: datetime
    impact: str                 # "high" | "medium" | "low"
    major: bool = False         # FOMC / NFP → longer blackout


class NewsProvider(Protocol):
    def upcoming(self, symbol: str, around: datetime) -> list[NewsEvent]: ...
    def available(self) -> bool: ...


class NullNewsProvider:
    """Default: no feed connected. Honest about it."""

    def available(self) -> bool:
        return False

    def upcoming(self, symbol: str, around: datetime) -> list[NewsEvent]:
        return []


class StaticNewsProvider:
    """A provider seeded from a manually-curated list (e.g. loaded from DB/JSON).
    Useful for testing and for operators who maintain their own calendar."""

    def __init__(self, events: list[NewsEvent]):
        self._events = events

    def available(self) -> bool:
        return True

    def upcoming(self, symbol: str, around: datetime) -> list[NewsEvent]:
        return self._events


def evaluate_news(
    *,
    provider: NewsProvider,
    symbol: str,
    at: datetime,
    before_min: int = 30,
    after_min: int = 30,
    major_before_min: int = 60,
    major_after_min: int = 60,
    fail_safe_block: bool = True,
) -> NewsStatus:
    """Return CLEAR / BLOCKED / UNAVAILABLE for the given instant."""
    if at.tzinfo is None:
        at = at.replace(tzinfo=timezone.utc)
    if not provider.available():
        # No verified feed: honest 'unavailable'. Caller decides via fail policy.
        return NewsStatus.UNAVAILABLE

    for ev in provider.upcoming(symbol, at):
        if ev.impact != "high":
            continue
        b = major_before_min if ev.major else before_min
        a = major_after_min if ev.major else after_min
        start = ev.at - timedelta(minutes=b)
        end = ev.at + timedelta(minutes=a)
        if start <= at <= end:
            return NewsStatus.BLOCKED
    return NewsStatus.CLEAR


def news_blocks_trade(status: NewsStatus, *, use_news_filter: bool, fail_safe_block: bool = True) -> bool:
    """Translate a status into a go/no-go given config."""
    if not use_news_filter:
        return False
    if status is NewsStatus.BLOCKED:
        return True
    if status is NewsStatus.UNAVAILABLE:
        return fail_safe_block   # default: block when we can't verify
    return False
