"""Timezone- and DST-aware session filter (rulebook §13, F-SESS).

Session windows are defined in a named timezone (via the tz database, which
handles daylight saving automatically). We convert the signal's UTC bar time into
each session's local timezone and test membership. No session time is ever
hard-coded without timezone conversion.
"""
from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, time, timedelta, timezone
from zoneinfo import ZoneInfo


@dataclass(frozen=True)
class SessionWindow:
    name: str
    start: time      # local time in `tz`
    end: time
    tz: str
    # optional index refinements
    avoid_first_minutes: int = 0
    avoid_last_minutes: int = 0


def _local_time(dt_utc: datetime, tz: str) -> datetime:
    if dt_utc.tzinfo is None:
        dt_utc = dt_utc.replace(tzinfo=timezone.utc)
    return dt_utc.astimezone(ZoneInfo(tz))


def in_window(dt_utc: datetime, w: SessionWindow) -> bool:
    """True if the (UTC) instant falls inside the session, honoring DST and the
    optional open/close avoidance buffers."""
    local = _local_time(dt_utc, w.tz)
    # Build DST-correct start/end datetimes for that local day.
    start_dt = local.replace(hour=w.start.hour, minute=w.start.minute, second=0, microsecond=0)
    end_dt = local.replace(hour=w.end.hour, minute=w.end.minute, second=0, microsecond=0)
    if w.avoid_first_minutes:
        start_dt = start_dt + timedelta(minutes=w.avoid_first_minutes)
    if w.avoid_last_minutes:
        end_dt = end_dt - timedelta(minutes=w.avoid_last_minutes)
    return start_dt <= local <= end_dt


def is_tradeable(dt_utc: datetime, windows: list[SessionWindow]) -> bool:
    """True if the instant is inside ANY permitted session window."""
    return any(in_window(dt_utc, w) for w in windows)
