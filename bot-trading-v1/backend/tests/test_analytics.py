import math
from datetime import datetime, timezone

from app.services.analytics import ClosedTrade, compute_metrics


def _t(pnl, r, side="BUY", session="new_york", day=(2026, 7, 30)):
    return ClosedTrade(symbol="XAUUSD", side=side, pnl=pnl, r_multiple=r,
                       session=session, closed_at=datetime(*day, tzinfo=timezone.utc))


def test_empty_returns_zeroed():
    m = compute_metrics([])
    assert m.trades == 0 and m.net_profit == 0.0


def test_core_metrics():
    # 3 wins of +2R (+200), 2 losses of -1R (-100)
    trades = [_t(200, 2.0), _t(200, 2.0), _t(200, 2.0), _t(-100, -1.0), _t(-100, -1.0)]
    m = compute_metrics(trades)
    assert m.trades == 5 and m.wins == 3 and m.losses == 2
    assert m.net_profit == 400.0
    assert m.gross_profit == 600.0 and m.gross_loss == 200.0
    assert m.profit_factor == 3.0
    assert m.win_rate == 0.6
    assert round(m.avg_r, 3) == round((2+2+2-1-1)/5, 3)
    # expectancy = 0.6*2 + 0.4*(-1) = 0.8 R
    assert round(m.expectancy, 3) == 0.8


def test_max_drawdown_and_consec_losses():
    # curve: +100, -50, -50, -50, +100  -> peak 100, trough -50 => dd 150
    trades = [_t(100, 1), _t(-50, -0.5), _t(-50, -0.5), _t(-50, -0.5), _t(100, 1)]
    m = compute_metrics(trades)
    assert m.max_drawdown == 150.0
    assert m.max_consecutive_losses == 3


def test_profit_factor_infinite_when_no_losses():
    m = compute_metrics([_t(100, 1), _t(50, 0.5)])
    assert math.isinf(m.profit_factor)


def test_long_short_and_session_breakdown():
    trades = [_t(100, 1, side="BUY"), _t(-100, -1, side="SELL", session="london")]
    m = compute_metrics(trades)
    assert m.breakdown["long"]["trades"] == 1
    assert m.breakdown["short"]["trades"] == 1
    assert "london" in m.breakdown["by_session"]
    assert "new_york" in m.breakdown["by_session"]
