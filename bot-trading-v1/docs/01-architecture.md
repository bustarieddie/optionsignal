# Part A — Technical Architecture

BOT TRADING v1.0

---

## A.1 Text architecture diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│  TradingView  —  BotTradingV1.pine (strategy, 5M chart)                    │
│  4H direction · 1H trend · 15M pullback · 5M trigger → alert() JSON        │
└───────────────────────────────┬──────────────────────────────────────────┘
                                 │  HTTPS POST (signed JSON)
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Webhook Receiver API   POST /webhook/tradingview                          │
│  • secret + HMAC verify   • schema validate   • idempotency (signal_id)    │
│  • timestamp/expiry check • rate limit        • raw-payload audit log      │
└───────────────────────────────┬──────────────────────────────────────────┘
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Signal Validation Engine                                                  │
│  • payload well-formed?      • signal not expired?                         │
│  • symbol known + mapped?    • price deviation vs latest broker price?     │
│  • side/context consistent?  • session + news filter status               │
│  → produces a normalized Signal or a RejectedSignal(reason)               │
└───────────────────────────────┬──────────────────────────────────────────┘
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Risk Management Engine                                                    │
│  • kill-switch / pause / risk-lock gate                                    │
│  • max trades/day, max losses/day, consecutive losses                     │
│  • daily / weekly loss limits    • one open trade per symbol              │
│  • one signal per candle         • correlated index-group exposure        │
│  • position sizing (contract/tick/lot aware)                              │
│  • stop distance min/max, spread, ATR volatility band                     │
│  → APPROVE(order_intent) or REJECT(reason)                                │
└───────────────────────────────┬──────────────────────────────────────────┘
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Broker Execution Adapter   (interface)                                    │
│  paper │ mock │ live-template     — chosen by ENVIRONMENT                  │
│  • place order → place protective SL/TP → confirm → reconcile             │
│  • emergency policy if SL placement fails                                  │
└───────────────────────────────┬──────────────────────────────────────────┘
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Broker / Trading Platform  (paper engine by default; real venue via      │
│  a per-broker live adapter that YOU implement and enable explicitly)       │
└───────────────────────────────┬──────────────────────────────────────────┘
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  PostgreSQL (trades, signals, risk snapshots, audit) · Redis (locks,      │
│  dedup, daily counters) · Notifications (Telegram/email/n8n) · Dashboard  │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## A.2 Component responsibilities

| Component | Responsibility | Must NOT do |
|-----------|----------------|-------------|
| **Pine strategy** | Detect the 4-layer setup on confirmed bars; emit a signed JSON alert with a unique `signal_id`. | Never repaint; never place orders; never hold secrets in source. |
| **Webhook Receiver** | Authenticate, validate schema, enforce idempotency, persist raw payload, hand off. | Never execute or size trades; never trust the payload's prices blindly. |
| **Signal Validation Engine** | Turn a raw payload into a normalized `Signal` or a `RejectedSignal(reason)`. Expiry, symbol mapping, price-deviation, session/news status. | Never touch account risk state. |
| **Risk Management Engine** | The single authority on "may we trade and how big?". Enforces every portfolio/risk rule and computes position size. | Never call the broker; never invent broker limits. |
| **Broker Execution Adapter** | Translate an approved `OrderIntent` into venue calls; place order + protective orders; reconcile; run the emergency policy. | Never decide *whether* to trade or *how big*. |
| **Persistence/Audit** | Immutable record of every decision, rejection, order and state transition. | Never store secrets in plaintext. |
| **Notifications** | Route events to Telegram/email/n8n/dashboard. | Never include secrets or full credentials. |
| **Admin** | Kill-switch, pause, resume, close-position, risk-status — role-protected. | Never bypass the risk lock silently (all actions audited). |

**Separation-of-powers principle:** the engine that decides *whether* to trade (Risk) is
distinct from the one that *sizes* the exposure (also Risk, but a pure function) and from the
one that *executes* (Broker adapter). No single module can both approve and execute without
an audit record.

---

## A.3 Data flow (happy path + rejection)

```
Pine bar closes → setup valid → alert() emits JSON
  → POST /webhook/tradingview
     → [Receiver] verify secret+HMAC ─ fail → 401, log rejected_signals(REJECT_BAD_SIGNATURE)
        → dedup by signal_id (Redis SETNX) ─ dup → 200 idempotent, log REJECT_DUPLICATE_SIGNAL
           → schema valid? ─ no → 422, log REJECT_MALFORMED
              → [Validation] expired? ─ yes → log REJECT_SIGNAL_EXPIRED
                 → symbol known+mapped? ─ no → log REJECT_UNKNOWN_SYMBOL
                    → price deviation ≤ limit? ─ no → log REJECT_SIGNAL_DEVIATION
                       → session open + news clear? ─ no → REJECT_NEWS_WINDOW / REJECT_SESSION
                          → [Risk] gates + sizing
                             → any limit hit → REJECT_* (see rulebook §R-RISK)
                             → APPROVE(OrderIntent)
                                → [Broker] place entry
                                   → place SL/TP ─ fail → EMERGENCY policy
                                   → confirm + persist trade + risk snapshot
                                      → notify · update dashboard
```

Every arrow that ends in a `REJECT_*` writes a `rejected_signals` row **with the reason code**
and emits a notification (configurable). Nothing is silently dropped.

---

## A.4 Security model

| Layer | Control |
|-------|---------|
| Transport | HTTPS only in production (TLS terminated at reverse proxy). |
| Webhook auth | Shared **secret in body** + **HMAC-SHA256** signature header when the sender can sign. TradingView cannot compute per-payload HMAC, so the Pine payload carries the secret and the receiver *additionally* pins the source via an unguessable URL path token + optional IP allow-list. This limitation is documented honestly in `05-webhook-and-security.md`. |
| Idempotency | `signal_id` deduped in Redis (`SETNX` + TTL) → repeated deliveries never create duplicate orders. |
| Replay defense | `bar_time`/`timestamp` + `setup_expiry` reject stale/replayed signals. |
| Secrets | Only via environment variables / secret store. Never in source, logs, or notifications. Broker credentials encrypted at rest where stored. |
| AuthN/Z | Admin endpoints require a bearer token; role-based (`admin` vs `viewer`). Repeated auth failures trigger temporary lockout. |
| Rate limiting | Per-IP and per-endpoint. |
| Audit | Every state transition, decision and admin action recorded in `audit_logs` with a correlation ID. |
| Least privilege | Broker API keys scoped to trading only where the venue supports it; no withdrawal scope. |

---

## A.5 Failure handling (summary — full matrix in §31 of the rulebook)

| Failure | Response |
|---------|----------|
| Internet loss / broker timeout | Retry with backoff; if unresolved, transition `BROKER_DISCONNECTED`, block new entries, alert. |
| Repeated TradingView alerts | Idempotency dedup by `signal_id`; extra deliveries are no-ops. |
| Backend restart | State is reconstructed from DB + broker reconciliation on startup. |
| **Order opened but SL placement fails** | **Emergency policy**: retry SL → confirm position → if still unprotected, **close the position** → alert → pause that symbol. |
| Order accepted but response lost | Reconcile against broker `get_open_positions()`/`get_order_status()` before acting again (never double-send). |
| Position at broker but not local (or vice-versa) | Reconciliation job flags the divergence, alerts, and refuses new trades on that symbol until resolved. |
| DB outage | Fail closed — reject new trades (can't audit → don't trade); serve health as degraded. |
| News-feed failure | News filter reports `unavailable`; per config, either block (fail-safe) or allow-with-warning. Default in code = block when `use_news_filter=true` and feed down. |

---

## A.6 Environments

Three mutually exclusive runtime environments, selected by config:

- `backtest` — no live/paper execution; used by the analytics/backtest tooling.
- `paper` — the **default**; the paper engine simulates fills, spread, slippage, SL/TP.
- `live` — requires `LIVE_TRADING_ENABLED=true` **and** a matching `LIVE_CONFIRMATION_PHRASE`
  **and** a real live adapter to be wired. Any missing precondition ⇒ `REJECT_LIVE_TRADING_DISABLED`.

Paper and live results are stored in separate rows (`environment` column) and never mixed in
analytics.

---

### Files created (Part A)
- `docs/01-architecture.md`

### Assumptions
- One backend instance is authoritative for risk state; horizontal scaling requires the Redis
  locks described here (already used for dedup and the per-candle lock).
- The reverse proxy terminates TLS and forwards the original client IP for allow-listing.

### Known limitations
- TradingView cannot HMAC-sign per payload; see the honest discussion in `05-webhook-and-security.md`.
- No paid news calendar is bundled; the news module is an interface with an honest `unavailable` state.

### Next
Part B — the exact rulebook (`02-rulebook.md`).
