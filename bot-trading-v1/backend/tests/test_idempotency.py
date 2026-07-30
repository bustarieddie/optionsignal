from app.core.idempotency import InMemoryDedupStore, candle_key, signal_key


def test_first_claim_succeeds_duplicate_fails():
    store = InMemoryDedupStore()
    key = signal_key("XAUUSD-5-123-BUY")
    assert store.claim(key, ttl_seconds=60) is True
    # repeated webhook delivery of the same signal_id -> duplicate
    assert store.claim(key, ttl_seconds=60) is False
    assert store.claim(key, ttl_seconds=60) is False


def test_distinct_signals_independent():
    store = InMemoryDedupStore()
    assert store.claim(signal_key("A"), 60) is True
    assert store.claim(signal_key("B"), 60) is True


def test_candle_key_shape():
    k = candle_key("NAS100", "5", "2026-07-30T13:25:00Z")
    assert k == "botv1:candle:NAS100:5:2026-07-30T13:25:00Z"


def test_ttl_expiry_allows_reclaim(monkeypatch):
    store = InMemoryDedupStore()
    t = {"v": 1000.0}
    monkeypatch.setattr("app.core.idempotency.time.monotonic", lambda: t["v"])
    assert store.claim("k", ttl_seconds=10) is True
    assert store.claim("k", ttl_seconds=10) is False
    t["v"] = 1011.0  # past ttl
    assert store.claim("k", ttl_seconds=10) is True
