from datetime import datetime, timedelta, timezone

from app.core import reject_codes as rc
from app.services.filters import build_session_windows, check_news, check_session
from app.services.news import NewsEvent, NullNewsProvider, StaticNewsProvider

SESSION_DEFS = {
    "new_york": {"start": "09:30", "end": "16:00", "tz": "America/New_York"},
    "london": {"start": "07:00", "end": "16:00", "tz": "Europe/London"},
}
SYMBOLS = {
    "NAS100": {"sessions": ["new_york"], "avoid_last_minutes": 5},
    "XAUUSD": {"sessions": ["london", "new_york"]},
}


def _utc(hh, mm, day=(2026, 7, 30)):
    return datetime(*day, hh, mm, tzinfo=timezone.utc)


def test_build_windows_applies_avoidance():
    w = build_session_windows(SYMBOLS, SESSION_DEFS)
    assert len(w["NAS100"]) == 1
    assert w["NAS100"][0].avoid_last_minutes == 5
    assert len(w["XAUUSD"]) == 2


def test_session_blocks_outside_hours():
    w = build_session_windows(SYMBOLS, SESSION_DEFS)
    # 03:00 UTC -> outside NY (which is 13:30-20:00 UTC in summer)
    assert check_session("NAS100", _utc(3, 0), w, enabled=True) == rc.REJECT_SESSION
    # 14:00 UTC == 10:00 EDT -> inside
    assert check_session("NAS100", _utc(14, 0), w, enabled=True) is None


def test_session_disabled_passes():
    w = build_session_windows(SYMBOLS, SESSION_DEFS)
    assert check_session("NAS100", _utc(3, 0), w, enabled=False) is None


def test_news_unavailable_blocks_by_default():
    filt = {"use_news_filter": True}
    assert check_news("XAUUSD", _utc(14, 0), NullNewsProvider(), filt) == rc.REJECT_NEWS_WINDOW


def test_news_blackout_window():
    at = _utc(14, 0)
    ev = NewsEvent("US CPI", at, impact="high")
    filt = {"use_news_filter": True, "news_before_min": 30, "news_after_min": 30}
    prov = StaticNewsProvider([ev])
    # 20 min before -> blocked
    assert check_news("XAUUSD", at - timedelta(minutes=20), prov, filt) == rc.REJECT_NEWS_WINDOW
    # 45 min before -> clear
    assert check_news("XAUUSD", at - timedelta(minutes=45), prov, filt) is None


def test_news_filter_off_passes():
    assert check_news("XAUUSD", _utc(14, 0), NullNewsProvider(), {"use_news_filter": False}) is None
