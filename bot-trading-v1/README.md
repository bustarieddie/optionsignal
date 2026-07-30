# BOT TRADING v1.0 — SYSTEM ARCHITECTURE AND EXACT RULEBOOK

A production-oriented, **rule-based multi-timeframe** trading system for Gold (XAUUSD) and
US indices (NAS100, US30, SPX500), designed to be objective, auditable and configurable.

> ## ⚠️ CRITICAL SAFETY WARNING
>
> ```
> Algorithmic trading involves substantial financial risk.
> Backtested or simulated performance does not guarantee future results.
> The system must be independently tested before real-money deployment.
> ```
>
> - **Paper mode is enabled by default. Live mode is disabled by default.**
> - Live orders are only ever placed when **both** `LIVE_TRADING_ENABLED=true` **and**
>   the correct `LIVE_CONFIRMATION_PHRASE` are configured.
> - This repository makes **no claim of profitability**. It is an engineering framework.
> - No martingale, no averaging down, no automatic risk escalation. Ever.

---

## What this is (and is not)

BOT TRADING v1.0 is a **separate subsystem** that lives under `bot-trading-v1/`. It does
**not** modify the existing OptionSignal Pro Laravel app in this repository — the two are
independent. OptionSignal Pro is a US-*options* decision-support tool; BOT TRADING v1.0 is a
forex/CFD/index **execution framework** with a modular broker adapter, paper-trading engine
and risk engine.

The strategy uses strict multi-timeframe confirmation. A trade is **only** opened when every
required layer is valid:

```
4H  Market Direction
 ↓
1H  Trend Confirmation
 ↓
15M Pullback Setup
 ↓
5M  Entry Trigger
 ↓
Risk Validation
 ↓
Order Execution   (paper by default)
```

---

## Deliverables map

| Part | Deliverable | Location |
|------|-------------|----------|
| A | Technical Architecture | [`docs/01-architecture.md`](docs/01-architecture.md) |
| B | Exact Trading Rulebook (rule table) | [`docs/02-rulebook.md`](docs/02-rulebook.md) |
| C | State Machine | [`docs/03-state-machine.md`](docs/03-state-machine.md) |
| D | Project Structure | [`docs/04-project-structure.md`](docs/04-project-structure.md) |
| — | Webhook payload + security model | [`docs/05-webhook-and-security.md`](docs/05-webhook-and-security.md) |
| — | Deployment & operations | [`docs/06-deployment.md`](docs/06-deployment.md) |
| E | Pine Script strategy | [`pine/BotTradingV1.pine`](pine/BotTradingV1.pine) |
| F | Backend foundation (FastAPI) | [`backend/`](backend/) |
| — | Configuration | [`config/config.example.yaml`](config/config.example.yaml), [`config/symbols.example.yaml`](config/symbols.example.yaml) |
| Phase 4 | n8n workflows | [`n8n/`](n8n/) |

---

## Technology choices

| Concern | Choice | Why |
|---------|--------|-----|
| Signal generation | TradingView Pine Script **v6** | Native MTF, alerts, backtesting |
| Backend API | **Python 3.11 + FastAPI + Pydantic v2** | Strict typing, fast to audit, async |
| Persistent data | **PostgreSQL** (SQLite for local dev/tests) | Transactions, migrations |
| Locks / dedup / state | **Redis** (in-memory fallback for dev) | Atomic locks, idempotency keys |
| Deployment | **Docker + docker-compose** | Reproducible |
| Automation/notify | **n8n** (optional) | Telegram, summaries, error routing |
| Config | **YAML + environment variables** | No secrets in code |

> The recommendation in the master prompt allowed FastAPI **or** Node/TS. This build uses
> FastAPI. Nothing in the architecture depends on that choice — the broker adapter and
> module boundaries are language-agnostic.

---

## Quick start (paper mode)

```bash
cd bot-trading-v1/backend
cp ../.env.example .env                 # fill in a strong WEBHOOK_SECRET
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
pytest                                  # run the test suite
uvicorn app.main:app --reload           # starts in PAPER mode, LIVE disabled
```

Then send a signed test webhook (see `docs/05-webhook-and-security.md`).

To run the whole stack (API + Postgres + Redis) with Docker:

```bash
cd bot-trading-v1
cp .env.example .env
docker compose up --build
```

---

## Build status (honest)

| Component | Status |
|-----------|--------|
| Architecture, rulebook, state machine, project structure | ✅ Complete (docs) |
| Pine Script MTF strategy + JSON webhook | ✅ Complete, non-repainting |
| Config schema (system + per-symbol presets) | ✅ Complete |
| Webhook receiver + HMAC/secret validation + idempotency | ✅ Implemented |
| Signal validation engine (schema, expiry, price deviation) | ✅ Implemented |
| Risk engine (per-trade, daily/weekly, streak, correlation) | ✅ Implemented |
| Position sizing (contract/tick/lot aware) | ✅ Implemented + tested |
| Broker adapter interface + **paper** + **mock** + **live template** | ✅ Implemented |
| Emergency stop-placement policy | ✅ Implemented |
| Admin controls (kill-switch/pause/resume) | ✅ Implemented |
| Unit/integration tests (sizing, risk, dedup, expiry, session, kill-switch) | ✅ Implemented |
| Live broker integration (real venue) | ⛔ Intentionally a **template only** — must be wired per broker |
| News calendar feed | 🟡 Interface + "unavailable" honest default; no bundled paid feed |
| Dashboard | 🟡 JSON endpoints implemented; UI layout specified in docs |

See each phase's "Known limitations" section at the end of the relevant doc.
