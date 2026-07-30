"""Execution orchestrator + emergency protection policy (rulebook §31, §C.3).

Takes an APPROVED OrderIntent and places the entry, then the protective SL/TP.
CRITICAL RULE: if an order opens but the protective stop cannot be placed, the
system must immediately reduce risk per the emergency policy:

  1. Retry SL placement (bounded).
  2. Confirm the broker position exists.
  3. If still unprotected → CLOSE the unprotected position.
  4. Alert the administrator.
  5. Pause new trading for that symbol.
"""
from __future__ import annotations

from dataclasses import dataclass

from app.brokers.base import BrokerAdapter
from app.schemas.domain import OrderIntent


@dataclass
class ExecutionResult:
    ok: bool
    position_id: str | None
    protected: bool
    emergency_closed: bool = False
    paused_symbol: str | None = None
    reason: str | None = None
    alerts: tuple[str, ...] = ()


def execute(
    intent: OrderIntent,
    broker: BrokerAdapter,
    *,
    sl_retries: int = 2,
    notifier=None,
) -> ExecutionResult:
    sig = intent.signal
    alerts: list[str] = []

    entry = broker.place_market_order(
        sig.broker_symbol, sig.side, intent.lots, client_order_id=sig.signal_id
    )
    if not entry.ok or not entry.order_id:
        return ExecutionResult(False, None, False, reason=entry.reason or "entry_failed")

    pid = entry.order_id

    # Attempt protection, with bounded retries.
    protected = False
    for _ in range(sl_retries + 1):
        prot = broker.set_protection(pid, stop_loss=sig.stop_loss, take_profit=sig.take_profit)
        if prot.ok:
            # Verify the stop actually stuck at the broker (orphan-protection guard).
            positions = {p.position_id: p for p in broker.get_open_positions()}
            live = positions.get(pid)
            if live is not None and live.stop_loss is not None:
                protected = True
                break

    if protected:
        return ExecutionResult(True, pid, True)

    # --- EMERGENCY POLICY ---
    alerts.append(f"EMERGENCY: protection failed for {sig.broker_symbol} pos {pid}")
    still_open = any(p.position_id == pid for p in broker.get_open_positions())
    emergency_closed = False
    if still_open:
        close = broker.close_position(pid)
        emergency_closed = close.ok
        alerts.append(
            f"EMERGENCY: {'closed' if close.ok else 'FAILED TO CLOSE'} unprotected {pid}"
        )
    alerts.append(f"EMERGENCY: pausing new trades on {sig.symbol}")
    if notifier:
        for a in alerts:
            notifier(a)

    return ExecutionResult(
        ok=False,
        position_id=pid,
        protected=False,
        emergency_closed=emergency_closed,
        paused_symbol=sig.symbol,
        reason="protection_failed_emergency",
        alerts=tuple(alerts),
    )
