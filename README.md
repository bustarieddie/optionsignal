# OptionSignal Pro

A web-based **US stock options trading decision-support system**. It tracks TradingView
signals, scores their confidence, journals trades, manages risk, backtests, and exposes a
secure MCP server for Claude — built on Laravel 12 and the Sneat Bootstrap admin theme.

> **OptionSignal Pro is NOT financial advice and does NOT execute trades.** It is a decision-support
> tool for signal tracking, strategy journaling, alerts, risk management and backtesting. Always verify
> the live option chain manually before trading.

---

## Features

- **Auth** — register / login / email verification / optional TOTP 2FA (Laravel Fortify), API tokens (Sanctum), roles **Admin / Trader / Viewer** (spatie/laravel-permission).
- **Dashboard** — net & daily P/L, win rate, signal accuracy, best ticker, latest signals, active ideas, open trades, watchlist; live-updating via WebSocket.
- **Watchlist** — per-user US tickers (seeded: NVDA, TSLA, META, AAPL, SPY, QQQ, AMD, MSFT).
- **Strategies** — customizable strategies + rules; a system default *EMA 9/21 RSI Option Scalping Strategy* is seeded.
- **TradingView webhook** — `POST /api/webhooks/tradingview`, body-secret validation, rate limiting, raw logging, symbol allow-list, idempotent de-duplication.
- **Signal engine** — confidence scoring (EMA +20, RSI +20, VWAP +20, volume +15, HTF +15, S/R +10) → grade A+/A/B/C/ignore → option-contract criteria → broadcast + notify.
- **Risk management** — per-user max daily loss, max trades/day, risk per trade, stop/take-profit %, cooldown, no-trade window.
- **Trade journal** — entry/exit, P/L, setup, grade, emotion score, lessons, notes, screenshot uploads.
- **Backtesting** — import a TradingView "List of Trades" CSV (or generic CSV) → win rate, profit factor, expectancy, max drawdown, best ticker/timeframe/grade/setup.
- **Notifications** — database + email (+ optional Telegram), plus live dashboard push.
- **MCP server** — read-only-by-default tools for Claude (`get_watchlist`, `get_latest_signals`, …) with explicit `mcp:write` opt-in and full audit logging.
- **Admin panel** — users/roles, webhook logs, MCP audit logs, signal performance, risk defaults.

---

## Requirements

- PHP **8.3+**, Composer 2
- Node **20+** / npm
- SQLite (dev default) — or MySQL/PostgreSQL + Redis for production

---

## Quick start (development)

```bash
composer install
npm install --legacy-peer-deps        # template devDeps need legacy peer resolution
cp .env.example .env
php artisan key:generate

# SQLite db (Windows: New-Item database\database.sqlite ; macOS/Linux: touch database/database.sqlite)

php artisan migrate --seed             # schema + roles, default strategy, demo users, watchlists

# Build front-end assets — run TWICE on a clean checkout (see note below)
npm run build
npm run build
```

Set a webhook secret in `.env`:

```
TRADINGVIEW_WEBHOOK_SECRET=your-long-random-string
```

### Run everything

```bash
composer run dev
```

This starts (via `concurrently`): the PHP server, the **queue worker** (required to process
webhooks), **Reverb** (WebSocket), Pail logs, and Vite. Or run them separately:

```bash
php artisan serve
php artisan queue:work        # REQUIRED — webhooks are processed on the queue
php artisan reverb:start      # live dashboard updates (optional; degrades gracefully)
npm run dev
```

### Demo logins

| Role   | Email                        | Password   |
|--------|------------------------------|------------|
| Admin  | admin@optionsignal.local     | `password` |
| Trader | trader@optionsignal.local    | `password` |
| Viewer | viewer@optionsignal.local    | `password` |

Email verification links and mail are written to `storage/logs/laravel.log` in dev (`MAIL_MAILER=log`).

> ⚠️ **Two build gotchas on a clean checkout** (Sneat template quirks):
> 1. `npm run build` must run **twice** — the icon plugin generates `iconify.css` during the first
>    build, and the second build picks it up into the Vite manifest.
> 2. If you copy the template with `robocopy`, do **not** exclude directories named `vendor` — that
>    also drops `resources/assets/vendor/` (the theme SCSS/libs).

---

## TradingView setup

1. Open **Pine Script** in the app sidebar, copy the Pine v6 template (or `resources/pine/optionsignal-pro.pine`).
2. Add it to a TradingView chart; set the **secret** input to match `TRADINGVIEW_WEBHOOK_SECRET`.
3. Create an alert with condition **"Any alert() function call"**, webhook URL `https://your-host/api/webhooks/tradingview`.

The Pine script emits JSON whose keys match the webhook exactly:

```json
{
  "secret": "your_webhook_secret",
  "ticker": "NVDA", "timeframe": "5m", "signal": "buy_call",
  "price": 120.50, "strategy": "EMA_9_21_RSI",
  "ema9": 121.20, "ema21": 120.80, "rsi": 58, "rsi_ma": 52,
  "vwap": 119.90, "volume_status": "above_average",
  "htf_trend": "bullish", "sr_clear": true,
  "timestamp": "2026-06-05T09:35:00-04:00"
}
```

The `secret` travels **inside the body** because TradingView cannot send custom headers; it is
validated with a timing-safe compare and never persisted.

---

## REST API

Authenticate with a Sanctum bearer token (create one under **Account → API Tokens**).

| Method | Endpoint | Notes |
|--------|----------|-------|
| POST | `/api/webhooks/tradingview` | secret in body; rate-limited |
| GET  | `/api/signals` · `/api/signals/{ticker}` | |
| GET  | `/api/strategies` · `/api/watchlist` | |
| GET  | `/api/trades` · POST `/api/trades` | POST needs `trades:write` ability |
| GET  | `/api/performance` | |

---

## MCP (Claude) integration

The MCP server is exposed at `POST /mcp/optionsignal`, authenticated with a Sanctum token.

- **Read tools** (need `mcp:read`): `get_watchlist`, `get_latest_signals`, `get_signal_by_ticker`, `get_trade_journal`, `get_strategy_performance`.
- **Write tools** (need `mcp:write`): `create_trade_note`, `summarize_daily_trades`, `analyze_failed_trades`.

New tokens default to `mcp:read` only — grant `mcp:write` deliberately. Every call is recorded in
`mcp_audit_logs`; tools return secret-free projections and never execute broker actions.

### Connect Claude Desktop

Claude Desktop speaks stdio, so it reaches the HTTP MCP endpoint through the `mcp-remote` bridge,
which injects your Sanctum token.

1. In the app, go to **Account → API Tokens** and create a token with the **`mcp:read`** ability
   (add `mcp:write` only if you want the write tools). Copy it.
2. Open `claude_desktop_config.json` (Windows: `%APPDATA%\Claude\claude_desktop_config.json`,
   macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`) and merge in the
   block from [`docs/claude_desktop_config.example.json`](docs/claude_desktop_config.example.json),
   pasting your token into the `Authorization: Bearer …` header.
3. Make sure the app is running (`composer run dev`), then fully restart Claude Desktop.
4. The `optionsignal` server's tools appear in Claude's tool menu; calls are gated by the token's
   abilities and logged to `mcp_audit_logs` (viewable in **Admin → MCP Audit Logs**).

The MCP endpoint returns **401** without a valid token (verified), so never commit a token.

---

## Telegram alerts (optional)

Signal notifications can also be pushed to Telegram. It's off until you configure a bot:

1. In Telegram, message **@BotFather** → `/newbot`, follow the prompts, copy the **bot token**.
2. Get your **chat id**: message your new bot once, then open
   `https://api.telegram.org/bot<TOKEN>/getUpdates` and read `result[].message.chat.id`
   (for a group, add the bot to the group and use the group chat id, usually negative).
3. Set in `.env`:
   ```
   TELEGRAM_BOT_TOKEN=123456:ABC-your-token
   TELEGRAM_CHAT_ID=123456789
   ```
4. Run `php artisan config:clear`. Actionable signals (grade ≥ C) now also send a Telegram message.

The channel is self-contained (no extra package) and silently no-ops when unconfigured. To route
per-user instead of one global chat, add a `routeNotificationForTelegram()` method to the `User`
model returning that user's chat id.

---

## Real-time quote feed (optional)

The dashboard watchlist shows live prices, polled from a provider and pushed over Reverb:

- **Provider** (`QUOTE_PROVIDER`): `auto` (default) uses **Finnhub** when `FINNHUB_API_KEY` is set, otherwise a **keyless Yahoo** fallback. Get a free key at [finnhub.io](https://finnhub.io).
- Run the poller: `php artisan quotes:poll --watch` (loops every `QUOTE_POLL_SECONDS`, default 15). It's already included in `composer run dev:reverb`.
- A one-shot `php artisan quotes:poll` fetches once (handy for cron/testing).
- Quotes are cached (`/quotes` serves the latest snapshot for initial page load) and broadcast on the public `quotes` channel; the UI updates live via `osp-quotes.js`. Degrades gracefully when the feed/Reverb is off.
- Decision-support only — quotes may be delayed and are not guaranteed accurate.

## Production notes

- Switch `.env` to MySQL/PostgreSQL and set `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.
- Run `php artisan queue:work` under a supervisor and `php artisan reverb:start` behind TLS.
- `php artisan config:cache route:cache view:cache` and `npm run build`.

## Testing

```bash
php artisan test
```

Covers: auth/dashboard rendering & role gating, webhook → graded signal (incl. dedupe & secret
stripping), confidence scoring/grading, and backtest metrics.

## Architecture

Thin controllers → `FormRequest` validation → `Service` classes → queued `Job`s → `Event`s →
`Notification`s/broadcast. The signal flow:

```
TradingView → POST /api/webhooks/tradingview (secret + throttle + dedupe)
            → tradingview_webhooks (raw landing)
            → ProcessTradingViewSignal (queue): StrategyMatcher → ConfidenceScorer
              → SignalGrader → OptionContractSuggester → RiskEvaluator
            → signals + signal_scores + option_suggestions
            → SignalProcessed event → Reverb (private user channel) + SignalNotification
```

The full architecture, ERD and security plan live in the implementation plan that accompanied this build.
