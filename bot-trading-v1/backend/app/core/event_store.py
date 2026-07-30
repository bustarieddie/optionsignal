"""In-memory ring buffers for recent signals / rejections / trades (dashboard feed).

The DB (§22 models) is the durable system of record; this store is a fast,
dependency-light read model so the dashboard and /signals /trades endpoints show
live data without a query round-trip. Bounded so memory can't grow unbounded.
"""
from __future__ import annotations

from collections import deque
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


@dataclass
class SignalRecord:
    signal_id: str
    symbol: str
    side: str
    status: str                 # accepted | rejected | duplicate | execution_failed
    reason: str | None = None   # REJECT_* when not accepted
    correlation_id: str | None = None
    environment: str = "paper"
    at: str = field(default_factory=_now_iso)


@dataclass
class TradeRecord:
    symbol: str
    side: str
    size: float
    entry_price: float
    stop_loss: float | None
    take_profit: float | None
    position_id: str | None
    open_risk_percent: float
    environment: str = "paper"
    status: str = "open"        # open | closed
    exit_price: float | None = None
    pnl: float | None = None
    at: str = field(default_factory=_now_iso)


class EventStore:
    def __init__(self, maxlen: int = 200):
        self.signals: deque[SignalRecord] = deque(maxlen=maxlen)
        self.rejections: deque[SignalRecord] = deque(maxlen=maxlen)
        self.trades: deque[TradeRecord] = deque(maxlen=maxlen)

    def record_signal(self, rec: SignalRecord) -> None:
        self.signals.appendleft(rec)
        if rec.status != "accepted":
            self.rejections.appendleft(rec)

    def record_trade(self, rec: TradeRecord) -> None:
        self.trades.appendleft(rec)

    def recent_signals(self, n: int = 25) -> list[dict]:
        return [asdict(r) for r in list(self.signals)[:n]]

    def recent_rejections(self, n: int = 25) -> list[dict]:
        return [asdict(r) for r in list(self.rejections)[:n]]

    def recent_trades(self, n: int = 25) -> list[dict]:
        return [asdict(r) for r in list(self.trades)[:n]]
