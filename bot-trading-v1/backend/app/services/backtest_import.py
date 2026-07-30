"""Backtest trade importer (rulebook §26).

Turns a TradingView "List of Trades" CSV export (or a generic trades CSV) into
``ClosedTrade`` objects for ``services.analytics.compute_metrics``. Pure stdlib.

TradingView exports two rows per trade (Entry then Exit). We pair them by trade
number. A generic single-row CSV (one row per closed trade) is also supported.
"""
from __future__ import annotations

import csv
import io
from datetime import datetime

from app.services.analytics import ClosedTrade


def _num(v) -> float:
    if v is None:
        return 0.0
    s = str(v).replace(",", "").replace("$", "").strip()
    if s in ("", "-", "n/a", "N/A"):
        return 0.0
    return float(s)


def _dt(v):
    if not v:
        return None
    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S", "%Y-%m-%d %H:%M", "%Y-%m-%d"):
        try:
            return datetime.strptime(str(v).strip(), fmt)
        except ValueError:
            continue
    try:
        return datetime.fromisoformat(str(v).replace("Z", "+00:00"))
    except Exception:
        return None


def _lower_keys(row: dict) -> dict:
    return {(k or "").strip().lower(): v for k, v in row.items()}


def parse_tradingview_csv(text: str, *, symbol: str = "UNKNOWN") -> list[ClosedTrade]:
    """Parse a TradingView 'List of Trades' export. Rows come in Entry/Exit pairs
    keyed by 'Trade #'. Uses the Exit row's P&L and computes R from risk when
    available, else falls back to P&L sign."""
    reader = csv.DictReader(io.StringIO(text))
    by_trade: dict[str, dict] = {}
    order: list[str] = []
    for raw in reader:
        row = _lower_keys(raw)
        tnum = str(row.get("trade #") or row.get("trade") or row.get("#") or len(order) + 1)
        typ = (row.get("type") or "").lower()
        if tnum not in by_trade:
            by_trade[tnum] = {}
            order.append(tnum)
        if "entry" in typ:
            by_trade[tnum]["entry"] = row
            by_trade[tnum]["side"] = "SELL" if "short" in typ else "BUY"
        elif "exit" in typ:
            by_trade[tnum]["exit"] = row
        else:
            by_trade[tnum]["single"] = row

    trades: list[ClosedTrade] = []
    for tnum in order:
        rec = by_trade[tnum]
        src = rec.get("exit") or rec.get("single") or rec.get("entry") or {}
        entry = rec.get("entry", src)
        pnl = _num(src.get("profit") or src.get("net profit") or src.get("p&l") or src.get("pnl"))
        # R multiple: prefer an explicit column; else derive from run-up/drawdown not
        # reliably available, so approximate as sign only when risk is unknown.
        r = _num(src.get("r") or src.get("r multiple") or src.get("r-multiple"))
        if r == 0.0 and pnl != 0.0:
            r = 1.0 if pnl > 0 else -1.0
        side = rec.get("side", "BUY")
        trades.append(ClosedTrade(
            symbol=symbol, side=side, pnl=pnl, r_multiple=r,
            opened_at=_dt(entry.get("date/time") or entry.get("date")),
            closed_at=_dt(src.get("date/time") or src.get("date")),
            session=None,
        ))
    return trades


def parse_generic_csv(text: str) -> list[ClosedTrade]:
    """One row per closed trade. Recognized columns (case-insensitive):
    symbol, side, pnl|profit, r|r_multiple, session, closed_at|date."""
    reader = csv.DictReader(io.StringIO(text))
    out: list[ClosedTrade] = []
    for raw in reader:
        row = _lower_keys(raw)
        pnl = _num(row.get("pnl") or row.get("profit"))
        r = _num(row.get("r") or row.get("r_multiple"))
        if r == 0.0 and pnl != 0.0:
            r = 1.0 if pnl > 0 else -1.0
        out.append(ClosedTrade(
            symbol=(row.get("symbol") or "UNKNOWN").upper(),
            side=(row.get("side") or "BUY").upper(),
            pnl=pnl, r_multiple=r,
            closed_at=_dt(row.get("closed_at") or row.get("date")),
            session=row.get("session") or None,
        ))
    return out
