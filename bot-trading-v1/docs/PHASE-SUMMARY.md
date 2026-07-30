# BOT TRADING v1.0 — Phase Summaries

Consolidated "Files created / Tests / Assumptions / Known limitations / Next" for
each phase, as required by the master prompt (§34, §36).

---

## Phase 1 — Architecture & Rulebook ✅

**Files:** `README.md`, `docs/01-architecture.md`, `docs/02-rulebook.md`,
`docs/03-state-machine.md`, `docs/04-project-structure.md`,
`docs/05-webhook-and-security.md`, `docs/06-deployment.md`,
`config/config.example.yaml`, `config/symbols.example.yaml`.

**Tests:** N/A (documentation). The rulebook's numeric rules are the test oracle
for later phases.

**Assumptions:** one authoritative backend instance for risk state; symmetric
pivots (3/3); Asia/Kuching daily reset; contract/tick values are broker-specific
placeholders to be confirmed.

**Known limitations:** TradingView cannot HMAC-sign payloads (defense-in-depth
used instead); no bundled news feed (honest `unavailable`); Fib pullback off by
default.

**Next:** Pine Script strategy.

---

## Phase 2 — Pine Script ✅

**Files:** `pine/BotTradingV1.pine` — a v6 **strategy** (backtestable) attached to
a 5M chart, pulling 15M/1H/4H via `request.security` with `lookahead_off`.

Implements: 4H direction (D-1..D-5), 1H trend (T-0..T-6), 15M pullback
(P-0..P-5), 5M trigger (E-1..E-5), structural/ATR/trigger stops, RR targets,
non-repaint gating on confirmed bars, JSON `alert()` payload matching the backend
schema, alert conditions, backtest date window + commission/slippage, and an
on-chart status panel (4H bias / 1H trend / 15M pullback / 5M trigger / ADX / ATR).

**Tests:** validate in TradingView using rulebook §27 Stage-1 checks (bar-replay
to confirm alerts fire only on confirmed closes; compare alert timestamps to
Strategy Tester entries). Non-repaint safeguards are documented in the file header.

**Assumptions:** `calc_on_every_tick=false` (confirmed values); pivot confirmation
delay of `pivotRight` bars is accepted and documented.

**Known limitations:** automated swing selection makes Fib zones approximate (off
by default); the expiry-bar conversion between 15M and 5M uses a chart-bar ratio.

**Next:** Backend foundation.

---

## Phase 3 — Backend foundation ✅

**Files:** `backend/app/` — `core/` (config, reject_codes, states, security,
idempotency, logging, runtime), `schemas/` (domain dataclasses + pydantic
webhook), `services/` (validation, position_sizing, risk_engine, sessions, news,
executor), `brokers/` (base interface, paper, mock, live_template), `api/routes/`
(webhook, admin, read), `db/` (all §22 models + session). Plus `requirements.txt`,
`Dockerfile`, `pytest.ini`.

**Tests (55, all passing — pure stdlib core, `pytest` only):**
- `test_position_sizing.py` — contract/tick/lot sizing, hard-max clamp, round-down,
  too-small, unreliable inputs, FX scaling.
- `test_risk_engine.py` — every R-rule reject code + happy path (max trades, daily/
  weekly loss, consecutive losses, one-per-symbol, one-per-candle, stop bounds,
  spread, open-risk cap, correlated exposure, live-disabled).
- `test_idempotency.py` — dedup claim/duplicate/TTL, candle key.
- `test_signal_expiry.py` — expiry, symbol-alias mapping, unknown symbol, price
  deviation, context mismatch, neutral 4H, no-trigger.
- `test_sessions.py` — DST-aware NY/London windows + open/close buffers.
- `test_webhook_security.py` — body-secret, url-token, IP allow-list, HMAC mode,
  admin lockout.
- `test_kill_switch.py` — state transitions block entries; history logged.
- `test_paper_broker.py` — paper fills w/ spread+slippage, PnL realize, disconnect
  reject; **executor emergency policy** closes unprotected positions + pauses symbol
  (including the orphan-protection case).

**Assumptions:** the tested core is dependency-light (dataclasses/stdlib); the HTTP
app layer needs `fastapi`/`pydantic`/`sqlalchemy` from `requirements.txt`.

**Known limitations:** live broker is a **template only** (`NotImplementedError` by
design); news provider defaults to `unavailable`.

**Next:** Phase 5 dashboard + analytics (below).

---

## Phase 5 — Dashboard + analytics ✅

**Files:** `dashboard/index.html` (self-contained responsive ops UI, served at
`/dashboard`), `backend/app/services/analytics.py` (§26 metrics),
`backend/app/core/event_store.py` (live read model for `/signals` `/trades`).

**Dashboard shows:** bot state · environment · **live-mode warning banner** (red,
pulsing, only when live is enabled) · broker/auth/news status · risk status
(equity, open-risk %, trades/losses today, consecutive losses, daily/weekly P/L)
· open positions · latest signals & rejection reasons · recent trades · admin
**pause / resume / kill-switch** buttons (bearer-token gated). Polls the read
endpoints every 5s; theme-aware (light/dark).

**Analytics (§26):** net/gross P&L, profit factor, win/loss rate, avg win/loss,
avg R, expectancy, max drawdown, max consecutive losses, Sharpe, Sortino, recovery
factor, long/short + session + day-of-week breakdowns. Reports only — never
optimises on net profit (§28).

**Live read model:** an in-memory ring buffer records every accepted/rejected
signal and executed trade so `/signals` and `/trades` return live data (the DB
§22 tables remain the durable record).

**Tests added (15):** `test_analytics.py` (metrics, drawdown, consec losses,
infinite PF, breakdowns), `test_news.py` (honest `unavailable`, fail-safe block,
blackout windows, major-event longer blackout, low-impact ignored),
`test_event_store.py` (accepted vs rejected routing, newest-first, bounded buffer).
**Total suite: 70 passing.**

**Known limitations:** dashboard is an internal ops tool (no auth on read
endpoints — deploy behind the reverse proxy / IP allow-list); analytics consumes
closed trades supplied by the caller (feed it from `trades` in production).

**Next:** Phase 6 deployment is documented in `docs/06-deployment.md`. See the
follow-up phase below for the live adapter, news feed and migrations.

---

## Follow-up — Live adapter · news feed · filters · migrations ✅

**A. Session + news filters wired into the live pipeline** (`services/filters.py`).
The webhook pipeline now applies F-SESS (timezone/DST-aware session windows built
per symbol from config) and F-NEWS between validation and risk, recording
`REJECT_SESSION` / `REJECT_NEWS_WINDOW` with reasons. A real, honest news source is
available: `JsonFileNewsProvider` loads an operator-maintained calendar
(`config/news_calendar.example.json`); set `NEWS_CALENDAR_FILE` to activate it.
With no file the status stays `unavailable` and (default) blocks fail-safe.

**B. Concrete live broker — OANDA v20** (`brokers/oanda.py`). A faithful adapter
against OANDA's documented v20 REST API (account summary, pricing, market/stop/
limit orders, STOP_LOSS/TAKE_PROFIT on open trades, openTrades, close). Pure
translation/parse helpers are unit-tested; the full order→protect→close flow is
tested via an injected fake HTTP client (no network, no `httpx` needed for tests).
Selected **only** when `env=live` + LIVE_* gates pass + `BROKER_KIND=oanda`; the
executor's emergency policy still guards a failed protective stop.

**C. Alembic migrations** (`backend/alembic.ini`, `backend/migrations/`). `env.py`
targets `app.db.models.Base.metadata` and reads `DATABASE_URL` from the env, so
`alembic revision --autogenerate` produces an initial migration that matches all
§22 tables. `compare_type=True`. See `migrations/README.md`.

**Tests added (17):** `test_oanda.py` (helpers + full flow via fake client),
`test_filters.py` (session windows + buffers, session/news gating). **Total suite:
87 passing.**

**Known limitations:** OANDA credentials/instrument specs must be confirmed per
account before live use; the news calendar is only as good as the file you
maintain; migrations are generated at deploy time (not committed here) so they
bind to your chosen DB.

---

## Safety posture (all phases)

Paper mode default · live disabled by default · global kill-switch · per-symbol
pause · daily/weekly loss lock · duplicate-order prevention · emergency close
policy · full audit logging · no martingale / no averaging down / no risk
escalation · **no profitability claims**.
