# Phase 4 — n8n workflows (optional automation layer)

n8n is an **optional** layer for notifications and automation. The backend works
without it. Two recommended roles:

## 1. Signing proxy (enables real HMAC)
TradingView cannot HMAC-sign its payload (see `../docs/05-webhook-and-security.md`).
Route TradingView → an n8n **Webhook** node → **Crypto** node (HMAC-SHA256 over the
raw body with `WEBHOOK_SECRET`) → **HTTP Request** node forwarding to
`https://<host>/webhook/tradingview/<URL_TOKEN>` with `X-Signature: sha256=<hmac>`.
Then set `HMAC_REQUIRED=true` in the backend. This upgrades webhook auth from
"body secret" to true per-payload HMAC.

## 2. Notification + logging fan-out
Subscribe n8n to backend notifications (or have the backend POST to `N8N_WEBHOOK_URL`)
and fan out:
- **Telegram** node → trade/risk/kill-switch alerts.
- **Google Sheets / DB** node → append every signal + rejection for an audit copy.
- **Daily summary** (Cron node) → pull `/admin/risk-status` + `/trades` → post a digest.
- **Error route** → on backend `state ∈ {BROKER_DISCONNECTED, EMERGENCY_STOP}`, page the operator.

## Importing
Build these in the n8n editor and export via **Download** to keep versioned JSON here
(e.g. `signing-proxy.json`, `notifications.json`). They are intentionally not
pre-generated because node IDs/credentials are instance-specific; the node recipe
above is exact enough to rebuild in minutes.

> Never place secrets in exported workflow JSON — use n8n credentials, referenced by name.
