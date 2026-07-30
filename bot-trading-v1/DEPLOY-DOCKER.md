# BOT TRADING v1.0 — VPS Docker deployment runbook

A concrete, copy-paste guide to run BOT TRADING v1.0 on a Linux VPS with Docker.
The bot starts in **paper mode with live trading disabled** — it stays that way
until you deliberately flip the live gates (last section).

> Verified before shipping: the app boots to `state: READY` and serves `/health`;
> the full test suite (128 tests) is green. The image builds from `backend/Dockerfile`
> + `backend/requirements.txt`; `docker-compose.yml` runs `api` + `db` (Postgres 16)
> + `redis` (7).

---

## 0. Prerequisites
- A small Linux VPS (1–2 vCPU / 2 GB RAM is plenty), root or sudo.
- A domain name pointed at the VPS (`A` record) — needed for HTTPS + the webhook URL.

## 1. Install Docker
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER" && newgrp docker
docker --version && docker compose version
```

## 2. Get the code
```bash
git clone https://github.com/bustarieddie/optionsignal.git
cd optionsignal/bot-trading-v1
```

## 3. Configure secrets (never commit the real .env)
```bash
cp .env.example .env
# Generate strong secrets:
echo "WEBHOOK_SECRET=$(openssl rand -hex 32)"
echo "URL_TOKEN=$(openssl rand -hex 16)"
echo "ADMIN_TOKEN=$(openssl rand -hex 24)"
nano .env      # paste the three values above; leave LIVE_TRADING_ENABLED=false
```
Minimum you must set in `.env`: `WEBHOOK_SECRET`, `URL_TOKEN`, `ADMIN_TOKEN`.
Keep `ENVIRONMENT=paper` and `LIVE_TRADING_ENABLED=false` for now.

Optional but recommended:
```
NEWS_CALENDAR_FILE=./config/news_calendar.example.json   # activates news protection
SCHEDULER_ENABLED=true                                   # auto-manage + daily reset
```
> Note: with the news filter ON and **no** calendar attached, the bot fail-safe
> **blocks** trades (`REJECT_NEWS_WINDOW`). That's intended. Attach a calendar or set
> `use_news_filter: false` in `config/config.yaml` if you want it off while testing.

## 4. Bring up the stack
```bash
docker compose up -d --build
docker compose ps                 # api, db, redis should be healthy
curl -s http://localhost:8000/health
# => {"status":"ok","state":"READY","environment":"paper","live_enabled":false,...}
```

## 5. Database migrations (production Postgres)
```bash
docker compose exec api sh -lc '
  export DATABASE_URL=postgresql+psycopg://botuser:botpass@db:5432/botdb
  alembic revision --autogenerate -m "init schema" && alembic upgrade head
'
```
(Dev/SQLite auto-creates tables; production should use these migrations.)

## 6. HTTPS reverse proxy (Caddy = automatic certs)
`/etc/caddy/Caddyfile`:
```
your-domain.com {
    reverse_proxy /webhook/* 127.0.0.1:8000
    reverse_proxy /admin/*   127.0.0.1:8000
    reverse_proxy /health    127.0.0.1:8000
    reverse_proxy /dashboard/* 127.0.0.1:8000
    reverse_proxy /positions  127.0.0.1:8000
    reverse_proxy /signals    127.0.0.1:8000
    reverse_proxy /trades     127.0.0.1:8000
    reverse_proxy /notifications 127.0.0.1:8000
}
```
```bash
sudo apt install -y caddy && sudo systemctl restart caddy
curl -s https://your-domain.com/health
```
> Only expose what you need. `/admin/*` is bearer-token protected, but consider an
> IP allow-list (`IP_ALLOWLIST` in `.env`) and/or restricting `/admin/*` at the proxy.

## 7. Point TradingView at the webhook
In the `BotTradingV1.pine` alert, set the webhook URL to:
```
https://your-domain.com/webhook/tradingview/<URL_TOKEN>
```
and set the Pine input **"Webhook secret"** to your `WEBHOOK_SECRET`. Use
alert condition **"Any alert() function call"**.

## 8. Dashboard
Open `https://your-domain.com/dashboard/` — paste your `ADMIN_TOKEN` to see risk
status and use pause/resume/kill-switch. A red banner appears if live is ever enabled.

## 9. Operate
```bash
docker compose logs -f api            # structured JSON logs
curl -s https://your-domain.com/health
# admin (bearer token):
curl -s -X POST https://your-domain.com/admin/pause      -H "Authorization: Bearer $ADMIN_TOKEN"
curl -s -X POST https://your-domain.com/admin/kill-switch -H "Authorization: Bearer $ADMIN_TOKEN"
```

## 10. Backups & updates
```bash
# nightly Postgres backup (cron)
docker compose exec -T db pg_dump -U botuser botdb | gzip > backup-$(date +%F).sql.gz
# update to latest code
git pull && docker compose up -d --build
```

## 11. Going LIVE (do NOT skip the ladder)
Run the full validation ladder first: backtest → out-of-sample → walk-forward →
Monte Carlo → **paper** → OANDA **practice** → small-risk live. Then, to enable a real
OANDA account, set in `.env` (all four required — any missing ⇒ `REJECT_LIVE_TRADING_DISABLED`):
```
ENVIRONMENT=live
LIVE_TRADING_ENABLED=true
LIVE_CONFIRMATION_PHRASE=<your value>          # must equal LIVE_CONFIRMATION_EXPECTED
LIVE_CONFIRMATION_EXPECTED=<same value>
BROKER_KIND=oanda
BROKER_ENV=practice                            # start on fxpractice, not fxtrade
BROKER_API_TOKEN=<oanda token, trading scope>
BROKER_ACCOUNT_ID=<oanda account id>
```
Confirm each instrument's `contract_size` in `config/symbols.yaml` against your OANDA
account, restart (`docker compose up -d`), and watch the dashboard's live warning turn on.
Start with a small `risk_per_trade_percent`.

---

**Safety reminder.** Algorithmic trading involves substantial financial risk.
Backtested/simulated performance does not guarantee future results. Test independently
before real-money deployment. This system makes no profitability claims.
