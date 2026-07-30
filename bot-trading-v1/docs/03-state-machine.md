# Part C — State Machine

BOT TRADING v1.0 supports the states below. **Every transition is logged** to `system_events`
with a correlation ID, the trigger, and the previous/next state.

## C.1 States

| State | Meaning | New entries allowed? |
|-------|---------|----------------------|
| `OFFLINE` | Process not running. | — |
| `STARTING` | Booting: load config, connect DB/Redis, reconcile broker. | No |
| `PAPER_MODE` | Running against the paper engine (default). | Yes (paper) |
| `LIVE_DISABLED` | Live requested but preconditions unmet → behaves read-only for execution. | No |
| `READY` | Connected, no active setup. | Yes |
| `MONITORING` | Evaluating 4H/1H/15M/5M layers. | Yes |
| `SETUP_ACTIVE` | A 15M pullback setup is armed, awaiting 5M trigger. | Yes |
| `ORDER_PENDING` | Order sent, awaiting fill/confirmation. | No (per symbol) |
| `POSITION_OPEN` | Position live and protected by SL/TP. | Per R-8 (one/symbol) |
| `PAUSED` | Operator paused new entries; open trades still managed. | No |
| `RISK_LOCKED` | A risk limit (R-2..R-10) tripped; awaits reset condition. | No |
| `BROKER_DISCONNECTED` | Broker unreachable; reconciliation pending. | No |
| `EMERGENCY_STOP` | Kill-switch or failed-protection emergency. Flatten/hold per policy. | No |

## C.2 Transition diagram

```
OFFLINE ──start──▶ STARTING
STARTING ──config ok, env=paper──▶ PAPER_MODE ──▶ READY
STARTING ──env=live & LIVE_TRADING_ENABLED & phrase ok──▶ READY(live)
STARTING ──env=live & precondition missing──▶ LIVE_DISABLED
STARTING ──broker unreachable──▶ BROKER_DISCONNECTED

READY ──signal in, layers evaluating──▶ MONITORING
MONITORING ──15M pullback armed──▶ SETUP_ACTIVE
SETUP_ACTIVE ──5M trigger + risk APPROVE──▶ ORDER_PENDING
SETUP_ACTIVE ──invalidation/expiry──▶ MONITORING
ORDER_PENDING ──filled + SL/TP placed──▶ POSITION_OPEN
ORDER_PENDING ──rejected/timeout──▶ MONITORING  (log; reconcile)
ORDER_PENDING ──filled but SL FAILS──▶ EMERGENCY_STOP (emergency policy)
POSITION_OPEN ──SL/TP/time/structure exit──▶ MONITORING
POSITION_OPEN ──trailing/partial──▶ POSITION_OPEN (self-loop, logged)

any(READY,MONITORING,SETUP_ACTIVE,POSITION_OPEN) ──risk limit hit──▶ RISK_LOCKED
RISK_LOCKED ──reset condition (daily/weekly/operator)──▶ READY
any ──operator pause──▶ PAUSED ──resume──▶ READY
any ──broker lost──▶ BROKER_DISCONNECTED ──reconnect+reconcile──▶ READY
any ──kill-switch or protection failure──▶ EMERGENCY_STOP
EMERGENCY_STOP ──operator clears + reconciled──▶ PAUSED
```

## C.3 Emergency policy (entering `EMERGENCY_STOP` from failed protection)

1. **Retry** SL placement (bounded retries, backoff).
2. **Confirm** the broker position actually exists (`get_open_positions`).
3. If still unprotected → **close** the unprotected position at market.
4. **Alert** the administrator (all channels).
5. **Pause** new trading for that symbol until an operator clears it.

## C.4 Reset conditions out of `RISK_LOCKED`

- Daily limits (R-3..R-6, R-9): reset at the daily boundary in `timezone` (default Asia/Kuching).
- Weekly limit (R-7): reset at the configured week start.
- Open-risk / correlation (R-2, R-10): clear automatically when positions close.
- Operator override: an audited admin action can lift a lock early (logged, requires admin role).

## C.5 Startup reconciliation (leaving `STARTING`)

On boot the bot compares local DB positions with `broker.get_open_positions()`:
- position at broker but not local → import + protect or flag for operator.
- position local but not at broker → mark closed/unknown + alert; never re-open blindly.
- only once reconciled does it proceed to `READY`.

---

### Files created (Part C)
- `docs/03-state-machine.md`

### Known limitations
- Cross-restart in-flight `ORDER_PENDING` relies on broker `get_order_status()` supporting an
  idempotent client order id; brokers without it require the operator to confirm (flagged, not guessed).

### Next
Part D — project structure (`04-project-structure.md`).
