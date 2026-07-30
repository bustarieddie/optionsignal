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
design); news provider defaults to `unavailable`; DB read endpoints for
`/signals` and `/trades` are stubbed pending the persistence wiring.

**Next:** Phase 4 (n8n) — see `n8n/README.md`; Phase 5 (dashboard) — endpoints
exist (`/health`, `/positions`, `/admin/risk-status`), UI layout specified in
`docs/../README` and §25 of the prompt.

---

## Safety posture (all phases)

Paper mode default · live disabled by default · global kill-switch · per-symbol
pause · daily/weekly loss lock · duplicate-order prevention · emergency close
policy · full audit logging · no martingale / no averaging down / no risk
escalation · **no profitability claims**.
