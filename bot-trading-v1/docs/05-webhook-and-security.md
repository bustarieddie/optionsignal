# Webhook Payload & Security Model

## 1. Payload schema (v1.0)

The Pine strategy emits this JSON via `alert()`. Extra fields improve auditing; the receiver
validates types and required fields (see `backend/app/schemas/webhook.py`).

```json
{
  "version": "1.0",
  "strategy_id": "MTF_GOLD_INDEX_V1",
  "signal_id": "XAUUSD-5-1730289000-BUY",
  "timestamp": "2026-07-30T13:30:00Z",
  "timezone": "Asia/Kuching",
  "symbol": "XAUUSD",
  "broker_symbol": "XAUUSD",
  "timeframe": "5",
  "side": "BUY",
  "entry_type": "RETEST",
  "entry_price": 2405.10,
  "stop_loss": 2401.20,
  "take_profit": 2412.90,
  "risk_reward": 2.0,
  "risk_percent": 0.5,
  "atr_5m": 1.95,
  "spread": 0.18,
  "direction_4h": "BULLISH",
  "trend_1h": "BULLISH",
  "pullback_15m": true,
  "trigger_5m": "BULLISH_BOS",
  "setup_expiry": "2026-07-30T16:30:00Z",
  "bar_time": "2026-07-30T13:25:00Z",
  "secret": "WEBHOOK_SECRET_PLACEHOLDER"
}
```

### Field rules
- `signal_id` — **globally unique**, deterministic from `symbol|timeframe|bar_time|side` so repeated
  TradingView deliveries carry the *same* id → deduplicated.
- `setup_expiry` — signal is rejected after this time (`REJECT_SIGNAL_EXPIRED`).
- `bar_time` — the confirmed bar's open time; used for the per-candle lock (R-9) and replay defense.
- `entry_price`/`stop_loss`/`take_profit` — advisory; the backend **re-validates against the latest
  broker price** and re-sizes. TradingView prices are never trusted for execution blindly.
- `secret` — matched to `WEBHOOK_SECRET` (constant-time compare).

## 2. Idempotency & duplicate prevention

1. `signal_id` is inserted into Redis with `SETNX` + TTL (`SIGNAL_DEDUP_TTL`, default 1h).
   A second delivery finds the key present → returns `200 {"status":"duplicate"}` and logs
   `REJECT_DUPLICATE_SIGNAL`. No order is created.
2. A **per-candle lock** (`symbol|timeframe|bar_time`) enforces R-9 (one new signal per candle).
3. At the DB layer, `signal_events.signal_id` is `UNIQUE`; order creation runs inside a
   transaction so a lost-response retry cannot double-execute.

## 3. Security model — honest limitations

**The hard truth:** TradingView's alert body is static text with placeholders; it **cannot compute
an HMAC over the payload it is about to send**. So a pure "sign every payload with HMAC" scheme is
not achievable from TradingView alone. This build therefore uses **defense in depth**:

| Control | What it gives you | Limitation |
|---------|-------------------|------------|
| **Shared secret in body** (`secret`) | Rejects senders who don't know the secret. Constant-time compared. | The secret travels in the body — protect it with HTTPS and rotate it. |
| **Unguessable URL path token** | The webhook URL contains a random token (`/webhook/tradingview/{token}`), so the endpoint isn't discoverable. | Still a bearer secret; rotate on leak. |
| **Optional IP allow-list** | Restrict to TradingView's published webhook IP ranges. | TradingView IPs can change; keep the list updated. |
| **HMAC header (when a signer sits in front)** | If you route TradingView → n8n/proxy that CAN sign, the receiver verifies `X-Signature: sha256=...` over the raw body. | Requires an intermediary; not TradingView-native. |
| **Timestamp + expiry** | Rejects replayed/stale payloads. | Clock skew tolerance configurable (`SIGNAL_MAX_SKEW_SEC`). |

**The code supports both modes.** If `HMAC_REQUIRED=true`, a valid `X-Signature` is mandatory
(use this when an n8n/proxy signer is in place). If `false` (TradingView-direct), the body secret +
URL token + optional IP allow-list are enforced. The system **never pretends** HMAC is active when
it isn't — `/health` reports the active auth mode.

## 4. Sending a signed test webhook (dev)

```bash
SECRET="your-webhook-secret"
BODY='{"version":"1.0","strategy_id":"MTF_GOLD_INDEX_V1","signal_id":"TEST-1",
"timestamp":"2026-07-30T13:30:00Z","timezone":"Asia/Kuching","symbol":"XAUUSD",
"broker_symbol":"XAUUSD","timeframe":"5","side":"BUY","entry_type":"RETEST",
"entry_price":2405.1,"stop_loss":2401.2,"take_profit":2412.9,"risk_reward":2.0,
"risk_percent":0.5,"atr_5m":1.95,"spread":0.18,"direction_4h":"BULLISH",
"trend_1h":"BULLISH","pullback_15m":true,"trigger_5m":"BULLISH_BOS",
"setup_expiry":"2026-07-30T16:30:00Z","bar_time":"2026-07-30T13:25:00Z",
"secret":"'"$SECRET"'"}'

# HMAC mode (optional intermediary):
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')
curl -sS -X POST http://localhost:8000/webhook/tradingview/$URL_TOKEN \
  -H "Content-Type: application/json" -H "X-Signature: sha256=$SIG" -d "$BODY"
```

## 5. Secret rotation guidance
- Store `WEBHOOK_SECRET`, `URL_TOKEN`, broker keys and admin tokens only in env/secret store.
- Rotate on any suspected leak: update `.env`, restart, update the TradingView alert body and URL.
- Broker credentials stored in DB (`broker_connections`) are encrypted with `SECRETS_ENC_KEY`.
- Never log secrets — the logger scrubs known secret keys (`core/logging.py`).
