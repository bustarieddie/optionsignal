from datetime import datetime, time, timezone

from app.services.sessions import SessionWindow, in_window, is_tradeable

NY = SessionWindow("new_york", time(9, 30), time(16, 0), "America/New_York")


def _utc(y, m, d, hh, mm):
    return datetime(y, m, d, hh, mm, tzinfo=timezone.utc)


def test_inside_ny_summer_edt():
    # 2026-07-30 14:00 UTC == 10:00 EDT -> inside 09:30-16:00
    assert in_window(_utc(2026, 7, 30, 14, 0), NY) is True


def test_outside_ny_summer():
    # 20:30 UTC == 16:30 EDT -> after close
    assert in_window(_utc(2026, 7, 30, 20, 30), NY) is False


def test_dst_awareness_winter_est():
    # 2026-01-15 14:00 UTC == 09:00 EST -> BEFORE 09:30 open (DST shifted)
    assert in_window(_utc(2026, 1, 15, 14, 0), NY) is False
    # 15:00 UTC == 10:00 EST -> inside
    assert in_window(_utc(2026, 1, 15, 15, 0), NY) is True


def test_avoid_last_minutes_buffer():
    ny_buf = SessionWindow("ny", time(9, 30), time(16, 0), "America/New_York", avoid_last_minutes=5)
    # 15:57 EDT (19:57 UTC) is within the last-5-min avoidance window -> excluded
    assert in_window(_utc(2026, 7, 30, 19, 57), ny_buf) is False
    # 15:50 EDT (19:50 UTC) still allowed
    assert in_window(_utc(2026, 7, 30, 19, 50), ny_buf) is True


def test_is_tradeable_any_window():
    london = SessionWindow("london", time(7, 0), time(16, 0), "Europe/London")
    assert is_tradeable(_utc(2026, 7, 30, 8, 0), [NY, london]) is True   # in london
    assert is_tradeable(_utc(2026, 7, 30, 3, 0), [NY, london]) is False  # neither
