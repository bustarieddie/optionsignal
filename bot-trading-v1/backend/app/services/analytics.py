"""Backtest / performance analytics (rulebook §26).

Pure functions over a list of closed trades. Computes the required metric set:
net/gross P&L, profit factor, win/loss rate, averages, R-multiple, expectancy,
max drawdown, max consecutive losses, Sharpe, Sortino, recovery factor, plus
long/short and session/day-of-week breakdowns.

Intentionally does NOT optimise on net profit alone (§28) — it just reports.
"""
from __future__ import annotations

import math
from dataclasses import dataclass, field
from datetime import datetime


@dataclass(frozen=True)
class ClosedTrade:
    symbol: str
    side: str                    # "BUY" | "SELL"
    pnl: float                   # account currency
    r_multiple: float            # pnl / initial risk
    opened_at: datetime | None = None
    closed_at: datetime | None = None
    session: str | None = None   # "london" | "new_york" | ...


@dataclass
class Metrics:
    trades: int = 0
    wins: int = 0
    losses: int = 0
    net_profit: float = 0.0
    gross_profit: float = 0.0
    gross_loss: float = 0.0
    profit_factor: float = 0.0
    win_rate: float = 0.0
    loss_rate: float = 0.0
    avg_win: float = 0.0
    avg_loss: float = 0.0
    avg_r: float = 0.0
    expectancy: float = 0.0
    max_drawdown: float = 0.0
    max_consecutive_losses: int = 0
    sharpe: float = 0.0
    sortino: float = 0.0
    recovery_factor: float = 0.0
    breakdown: dict = field(default_factory=dict)


def _std(xs: list[float]) -> float:
    if len(xs) < 2:
        return 0.0
    m = sum(xs) / len(xs)
    var = sum((x - m) ** 2 for x in xs) / (len(xs) - 1)
    return math.sqrt(var)


def _downside_std(xs: list[float]) -> float:
    neg = [x for x in xs if x < 0]
    if len(neg) < 1:
        return 0.0
    return math.sqrt(sum(x ** 2 for x in neg) / len(neg))


def _max_drawdown(pnls: list[float]) -> float:
    """Peak-to-trough of the cumulative equity curve (absolute currency)."""
    peak = 0.0
    equity = 0.0
    max_dd = 0.0
    for p in pnls:
        equity += p
        peak = max(peak, equity)
        max_dd = max(max_dd, peak - equity)
    return max_dd


def _max_consec_losses(pnls: list[float]) -> int:
    cur = best = 0
    for p in pnls:
        if p < 0:
            cur += 1
            best = max(best, cur)
        else:
            cur = 0
    return best


def compute_metrics(trades: list[ClosedTrade]) -> Metrics:
    m = Metrics(trades=len(trades))
    if not trades:
        return m

    pnls = [t.pnl for t in trades]
    rs = [t.r_multiple for t in trades]
    wins = [p for p in pnls if p > 0]
    losses = [p for p in pnls if p < 0]

    m.wins = len(wins)
    m.losses = len(losses)
    m.net_profit = sum(pnls)
    m.gross_profit = sum(wins)
    m.gross_loss = abs(sum(losses))
    m.profit_factor = (m.gross_profit / m.gross_loss) if m.gross_loss else math.inf
    m.win_rate = m.wins / m.trades
    m.loss_rate = m.losses / m.trades
    m.avg_win = (m.gross_profit / m.wins) if m.wins else 0.0
    m.avg_loss = (-m.gross_loss / m.losses) if m.losses else 0.0
    m.avg_r = sum(rs) / len(rs)
    # Expectancy per trade in R: win_rate*avgWinR - loss_rate*avgLossR
    win_r = [t.r_multiple for t in trades if t.pnl > 0]
    loss_r = [t.r_multiple for t in trades if t.pnl < 0]
    avg_win_r = (sum(win_r) / len(win_r)) if win_r else 0.0
    avg_loss_r = (sum(loss_r) / len(loss_r)) if loss_r else 0.0
    m.expectancy = m.win_rate * avg_win_r + m.loss_rate * avg_loss_r
    m.max_drawdown = _max_drawdown(pnls)
    m.max_consecutive_losses = _max_consec_losses(pnls)

    sd = _std(rs)
    m.sharpe = (m.avg_r / sd) if sd else 0.0
    dsd = _downside_std(rs)
    m.sortino = (m.avg_r / dsd) if dsd else 0.0
    m.recovery_factor = (m.net_profit / m.max_drawdown) if m.max_drawdown else math.inf

    m.breakdown = {
        "long": _side_summary([t for t in trades if t.side == "BUY"]),
        "short": _side_summary([t for t in trades if t.side == "SELL"]),
        "by_session": _group_summary(trades, key=lambda t: t.session or "unknown"),
        "by_day_of_week": _group_summary(
            trades, key=lambda t: t.closed_at.strftime("%A") if t.closed_at else "unknown"
        ),
    }
    return m


def _side_summary(trades: list[ClosedTrade]) -> dict:
    if not trades:
        return {"trades": 0, "net_profit": 0.0, "win_rate": 0.0}
    wins = sum(1 for t in trades if t.pnl > 0)
    return {
        "trades": len(trades),
        "net_profit": round(sum(t.pnl for t in trades), 4),
        "win_rate": round(wins / len(trades), 4),
    }


def _group_summary(trades: list[ClosedTrade], key) -> dict:
    groups: dict[str, list[ClosedTrade]] = {}
    for t in trades:
        groups.setdefault(key(t), []).append(t)
    return {k: _side_summary(v) for k, v in groups.items()}
