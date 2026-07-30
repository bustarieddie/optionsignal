"""Startup / periodic broker reconciliation (rulebook §C.5, §31).

Compares locally-tracked positions with what the broker actually reports and
classifies divergences. The bot must NOT proceed to READY for a symbol that has
an unresolved divergence, and must never blindly re-open. This module only
*detects*; the caller decides (alert + refuse new trades on that symbol).
"""
from __future__ import annotations

from dataclasses import dataclass, field

from app.brokers.base import BrokerPosition
from app.schemas.domain import Position


@dataclass
class ReconResult:
    matched: list[str] = field(default_factory=list)              # symbols present both sides
    at_broker_not_local: list[BrokerPosition] = field(default_factory=list)
    local_not_at_broker: list[Position] = field(default_factory=list)

    @property
    def clean(self) -> bool:
        return not self.at_broker_not_local and not self.local_not_at_broker

    def divergent_symbols(self) -> set[str]:
        s = {p.symbol for p in self.at_broker_not_local}
        s |= {p.symbol for p in self.local_not_at_broker}
        return s


def reconcile(local: list[Position], broker: list[BrokerPosition]) -> ReconResult:
    """One position per symbol is the invariant (R-8), so we key by symbol."""
    local_by = {p.symbol: p for p in local}
    broker_by = {p.symbol: p for p in broker}
    res = ReconResult()
    for sym in local_by.keys() | broker_by.keys():
        in_local = sym in local_by
        in_broker = sym in broker_by
        if in_local and in_broker:
            res.matched.append(sym)
        elif in_broker and not in_local:
            res.at_broker_not_local.append(broker_by[sym])
        elif in_local and not in_broker:
            res.local_not_at_broker.append(local_by[sym])
    return res
