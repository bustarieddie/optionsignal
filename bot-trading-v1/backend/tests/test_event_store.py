from app.core.event_store import EventStore, SignalRecord, TradeRecord


def test_accepted_signal_not_in_rejections():
    es = EventStore()
    es.record_signal(SignalRecord("s1", "XAUUSD", "BUY", status="accepted"))
    assert len(es.recent_signals()) == 1
    assert es.recent_rejections() == []


def test_rejected_signal_recorded_in_both():
    es = EventStore()
    es.record_signal(SignalRecord("s2", "NAS100", "SELL", status="rejected",
                                  reason="REJECT_SPREAD_HIGH"))
    assert len(es.recent_signals()) == 1
    assert len(es.recent_rejections()) == 1
    assert es.recent_rejections()[0]["reason"] == "REJECT_SPREAD_HIGH"


def test_newest_first():
    es = EventStore()
    es.record_signal(SignalRecord("a", "XAUUSD", "BUY", status="accepted"))
    es.record_signal(SignalRecord("b", "XAUUSD", "BUY", status="accepted"))
    assert es.recent_signals()[0]["signal_id"] == "b"


def test_ring_buffer_bounded():
    es = EventStore(maxlen=3)
    for i in range(5):
        es.record_signal(SignalRecord(f"s{i}", "XAUUSD", "BUY", status="accepted"))
    assert len(es.recent_signals(100)) == 3


def test_trade_recorded():
    es = EventStore()
    es.record_trade(TradeRecord("XAUUSD", "BUY", 0.1, 2400, 2396, 2408, "p1", 0.4))
    t = es.recent_trades()[0]
    assert t["symbol"] == "XAUUSD" and t["status"] == "open"
