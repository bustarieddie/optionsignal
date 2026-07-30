"""System states and transition logging (Part C — state machine)."""
from __future__ import annotations

import enum
from dataclasses import dataclass, field
from datetime import datetime, timezone


class SystemState(str, enum.Enum):
    OFFLINE = "OFFLINE"
    STARTING = "STARTING"
    PAPER_MODE = "PAPER_MODE"
    LIVE_DISABLED = "LIVE_DISABLED"
    READY = "READY"
    MONITORING = "MONITORING"
    SETUP_ACTIVE = "SETUP_ACTIVE"
    ORDER_PENDING = "ORDER_PENDING"
    POSITION_OPEN = "POSITION_OPEN"
    PAUSED = "PAUSED"
    RISK_LOCKED = "RISK_LOCKED"
    BROKER_DISCONNECTED = "BROKER_DISCONNECTED"
    EMERGENCY_STOP = "EMERGENCY_STOP"


# States in which brand-new entries are permitted.
ENTRY_ALLOWED = {SystemState.READY, SystemState.MONITORING, SystemState.SETUP_ACTIVE}


@dataclass
class Transition:
    prev: SystemState
    nxt: SystemState
    reason: str
    correlation_id: str | None = None
    at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))


class StateMachine:
    """Minimal in-memory state holder; every change is recorded."""

    def __init__(self, initial: SystemState = SystemState.OFFLINE):
        self.state = initial
        self.history: list[Transition] = []

    def transition(self, nxt: SystemState, reason: str, correlation_id: str | None = None) -> Transition:
        t = Transition(self.state, nxt, reason, correlation_id)
        self.history.append(t)
        self.state = nxt
        return t

    def entries_allowed(self) -> bool:
        return self.state in ENTRY_ALLOWED
