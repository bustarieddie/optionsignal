from datetime import datetime, timedelta, timezone

from app.services.news import (
    NewsEvent,
    NewsStatus,
    NullNewsProvider,
    StaticNewsProvider,
    evaluate_news,
    news_blocks_trade,
)


def _at(mins):
    return datetime(2026, 7, 30, 12, 0, tzinfo=timezone.utc) + timedelta(minutes=mins)


def test_no_feed_is_unavailable_not_clear():
    status = evaluate_news(provider=NullNewsProvider(), symbol="XAUUSD", at=_at(0))
    assert status is NewsStatus.UNAVAILABLE


def test_unavailable_blocks_when_fail_safe():
    # honest default: cannot verify -> block
    assert news_blocks_trade(NewsStatus.UNAVAILABLE, use_news_filter=True, fail_safe_block=True) is True
    # operator can opt to allow-with-warning
    assert news_blocks_trade(NewsStatus.UNAVAILABLE, use_news_filter=True, fail_safe_block=False) is False
    # filter off -> never blocks
    assert news_blocks_trade(NewsStatus.UNAVAILABLE, use_news_filter=False) is False


def test_blocked_inside_window():
    ev = NewsEvent("US CPI", _at(0), impact="high")
    prov = StaticNewsProvider([ev])
    # 20 min before -> within 30/30 window
    assert evaluate_news(provider=prov, symbol="XAUUSD", at=_at(-20)) is NewsStatus.BLOCKED
    # 45 min before -> clear
    assert evaluate_news(provider=prov, symbol="XAUUSD", at=_at(-45)) is NewsStatus.CLEAR


def test_major_event_longer_blackout():
    ev = NewsEvent("FOMC", _at(0), impact="high", major=True)
    prov = StaticNewsProvider([ev])
    # 45 min before is clear for normal (30) but BLOCKED for major (60)
    assert evaluate_news(provider=prov, symbol="XAUUSD", at=_at(-45),
                         major_before_min=60) is NewsStatus.BLOCKED


def test_low_impact_ignored():
    ev = NewsEvent("minor", _at(0), impact="low")
    prov = StaticNewsProvider([ev])
    assert evaluate_news(provider=prov, symbol="XAUUSD", at=_at(0)) is NewsStatus.CLEAR
