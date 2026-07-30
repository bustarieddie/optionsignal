"""End-to-end HTTP pipeline test via FastAPI TestClient.

Exercises the real route: auth -> schema -> idempotency -> validation -> filters
-> risk -> paper execution. Skipped automatically if the app-layer deps
(fastapi/pydantic/sqlalchemy/httpx) are not installed.
"""
from datetime import datetime, timedelta, timezone

import pytest

pytest.importorskip("fastapi")
pytest.importorskip("httpx")

from fastapi.testclient import TestClient  # noqa: E402

from app.core.config import load_settings  # noqa: E402
from app.core.runtime import build_runtime  # noqa: E402
from app.main import app  # noqa: E402

TOKEN = "test-url-token"
SECRET = "test-webhook-secret"


@pytest.fixture
def client():
    s = load_settings()
    s.webhook_secret = SECRET
    s.url_token = TOKEN
    s.hmac_required = False
    # Disable time-of-day/news gating so the pipeline is deterministic in CI.
    s.filters = {"use_session_filter": False, "use_news_filter": False, "signal_dev_atr": 0.25}
    app.state.runtime = build_runtime(s)   # bypass lifespan; inject a test runtime
    return TestClient(app)


def _payload(**over):
    now = datetime.now(timezone.utc)
    base = {
        "version": "1.0", "strategy_id": "MTF_GOLD_INDEX_V1",
        "signal_id": "ITEST-1", "timestamp": now.isoformat(), "timezone": "Asia/Kuching",
        "symbol": "XAUUSD", "broker_symbol": "XAUUSD", "timeframe": "5", "side": "BUY",
        "entry_type": "RETEST", "entry_price": 2400.0, "stop_loss": 2396.0,
        "take_profit": 2408.0, "risk_reward": 2.0, "risk_percent": 0.5, "atr_5m": 2.0,
        "direction_4h": "BULLISH", "trend_1h": "BULLISH", "pullback_15m": True,
        "trigger_5m": "BULLISH_BOS", "bar_time": now.isoformat(),
        "setup_expiry": (now + timedelta(minutes=60)).isoformat(), "secret": SECRET,
    }
    base.update(over)
    return base


def test_health(client):
    r = client.get("/health")
    assert r.status_code == 200
    body = r.json()
    assert body["environment"] == "paper"
    assert body["live_enabled"] is False


def test_accepts_valid_signal(client):
    r = client.post(f"/webhook/tradingview/{TOKEN}", json=_payload())
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "accepted"
    assert body["lots"] > 0
    # it now shows up in /trades and /signals
    assert client.get("/trades").json()["recent"]
    assert client.get("/positions").json()


def test_duplicate_is_idempotent(client):
    p = _payload(signal_id="ITEST-DUP")
    first = client.post(f"/webhook/tradingview/{TOKEN}", json=p)
    assert first.json()["status"] == "accepted"
    second = client.post(f"/webhook/tradingview/{TOKEN}", json=p)
    assert second.json()["status"] == "duplicate"


def test_bad_url_token_rejected(client):
    r = client.post("/webhook/tradingview/wrong-token", json=_payload())
    assert r.status_code == 401


def test_bad_secret_rejected(client):
    r = client.post(f"/webhook/tradingview/{TOKEN}", json=_payload(secret="nope"))
    assert r.status_code == 401


def test_expired_signal_rejected(client):
    now = datetime.now(timezone.utc)
    r = client.post(f"/webhook/tradingview/{TOKEN}",
                    json=_payload(signal_id="ITEST-EXP",
                                  setup_expiry=(now - timedelta(minutes=1)).isoformat()))
    assert r.json()["reason"] == "REJECT_SIGNAL_EXPIRED"


def test_malformed_rejected(client):
    r = client.post(f"/webhook/tradingview/{TOKEN}", json={"secret": SECRET, "not": "valid"})
    assert r.status_code == 422


def test_manage_tick_moves_breakeven(client):
    # open a partial-mode position, then send a manage tick at +1R -> breakeven move
    client.app.state.runtime.settings.strategy = {"exit_mode": "partial", "p1_r": 1.0, "be_r": 1.0, "p1_pct": 50}
    r = client.post(f"/webhook/tradingview/{TOKEN}", json=_payload(signal_id="ITEST-MANAGE"))
    assert r.json()["status"] == "accepted"
    pid = r.json()["position_id"]
    hdr = {"Authorization": "Bearer change-me"}
    # entry 2400, stop 2396 -> 1R at 2404
    m = client.post("/admin/manage-tick", params={"symbol": "XAUUSD", "price": 2404.0, "atr": 2.0}, headers=hdr)
    assert m.status_code == 200
    results = m.json()["results"]
    assert any(res["position_id"] == pid and "breakeven" in res["applied"] for res in results)


def test_kill_switch_then_signal_paused(client):
    # need admin token; default is 'change-me' when ADMIN_TOKEN unset
    hdr = {"Authorization": "Bearer change-me"}
    k = client.post("/admin/kill-switch", headers=hdr)
    assert k.status_code == 200 and k.json()["state"] == "EMERGENCY_STOP"
    r = client.post(f"/webhook/tradingview/{TOKEN}", json=_payload(signal_id="ITEST-KILL"))
    assert r.json()["reason"] == "REJECT_PAUSED"
