# Phase 6 — Deployment & Operations

## Local (paper mode)

```bash
cd bot-trading-v1/backend
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp ../.env.example .env            # set WEBHOOK_SECRET, URL_TOKEN
pytest                             # green suite before running
uvicorn app.main:app --reload      # http://localhost:8000/health
```

Defaults: `ENVIRONMENT=paper`, `LIVE_TRADING_ENABLED=false`. No live orders are possible.

## Docker (full stack)

```bash
cd bot-trading-v1
cp .env.example .env
docker compose up --build
# api → :8000, postgres → :5432, redis → :6379
```

`docker-compose.yml` runs three services: `api` (FastAPI + uvicorn), `db` (Postgres 16),
`redis` (7). The API waits for DB/Redis health before serving.

## VPS deployment (outline)

1. Provision a small VPS; install Docker + Docker Compose.
2. Put the API behind a reverse proxy (Caddy/Traefik/Nginx) that terminates **HTTPS** and
   forwards the real client IP (for the optional IP allow-list).
3. Set env vars via the host secret store, not a committed `.env`.
4. Point the TradingView alert at `https://your-host/webhook/tradingview/<URL_TOKEN>`.
5. Keep `LIVE_TRADING_ENABLED=false` until you have completed the validation ladder
   (`docs/../README.md` build status + rulebook §27).

### HTTPS
Use Caddy for automatic certs:

```
your-host {
  reverse_proxy /webhook/* api:8000
  reverse_proxy /admin/*   api:8000
  reverse_proxy /health    api:8000
}
```

## Database & migrations
- Dev/tests default to SQLite (`sqlite+pysqlite:///./bot.db`), zero setup.
- Production uses `DATABASE_URL=postgresql+psycopg://...`.
- Schema is created from `app/db/models.py` on startup for dev; for production use Alembic
  (a starter `alembic.ini`/migration can be generated from the models — see §22 of the prompt;
  models are migration-ready with `created_at`/`updated_at`/`correlation_id`/`environment`/`status`).

## Backups
- `pg_dump` on a cron schedule; keep N daily + M weekly snapshots off-box.
- Redis is ephemeral (locks/dedup/counters) — losing it costs at most a re-dedup window; the DB is
  the source of truth.

## Log rotation & monitoring
- Structured JSON logs to stdout → shipped by the container runtime; rotate at the platform level.
- `/health` returns `{state, environment, live_enabled, broker, db, redis, auth_mode}` for uptime
  checks. Alert if `state ∈ {BROKER_DISCONNECTED, EMERGENCY_STOP}` or `db != ok`.

## Recovery procedure
1. On restart the bot enters `STARTING`, connects DB/Redis, then runs broker **reconciliation**
   (state-machine §C.5) before `READY`.
2. If a divergence is found (position at broker not local, or vice-versa) it stays out of `READY`
   for that symbol, alerts, and refuses new trades on it until an operator resolves it.
3. The global **kill-switch** (`POST /admin/kill-switch`) forces `EMERGENCY_STOP`; clearing it
   requires an audited admin action after reconciliation.

## Going live (do not skip)
Backtest → in-sample → out-of-sample → walk-forward → Monte Carlo → **paper** → small-risk live.
Only then enable live. Never go backtest → full-size live.

### Enabling the OANDA v20 live adapter (example)
A concrete, faithful OANDA adapter ships in `backend/app/brokers/oanda.py`. To use it:

1. Confirm each instrument's contract/units with your OANDA account (`XAU_USD`,
   `NAS100_USD`, `US30_USD`, `SPX500_USD`) and set `contract_size` in `symbols.yaml`.
2. Run migrations against your production DB (`migrations/README.md`).
3. Set **all** of these (all four are required — any missing ⇒ `REJECT_LIVE_TRADING_DISABLED`):
   ```
   ENVIRONMENT=live
   LIVE_TRADING_ENABLED=true
   LIVE_CONFIRMATION_PHRASE=<your value>   # must equal LIVE_CONFIRMATION_EXPECTED
   BROKER_KIND=oanda
   BROKER_ENV=practice                     # start on fxpractice, not fxtrade
   BROKER_API_TOKEN=<oanda token, trading scope only>
   BROKER_ACCOUNT_ID=<oanda account id>
   ```
4. Start on `BROKER_ENV=practice` (OANDA demo) first; only switch to `live` after a
   clean paper + practice run. Use a small `risk_per_trade_percent` for initial live validation.

For any other venue (MT5, cTrader, IBKR…), copy `brokers/live_template.py` and implement
the interface the same way — the executor and emergency policy are broker-agnostic.
