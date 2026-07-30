from app.services.notifications import (
    NotificationDispatcher,
    RecordingChannel,
)


def test_dispatch_to_enabled_channel():
    ch = RecordingChannel()
    d = NotificationDispatcher([ch], notify_on={"order_placed": True})
    n = d.notify("order_placed", "XAUUSD BUY 0.1 lots")
    assert n == 1
    assert d.recent()[0]["event"] == "order_placed"


def test_disabled_event_is_noop():
    ch = RecordingChannel()
    d = NotificationDispatcher([ch], notify_on={"rejected_signal": False})
    assert d.notify("rejected_signal", "nope") == 0
    assert d.recent() == []


def test_unknown_event_defaults_on():
    ch = RecordingChannel()
    d = NotificationDispatcher([ch], notify_on={})
    assert d.notify("kill_switch", "boom") == 1


def test_secrets_scrubbed_from_fields():
    ch = RecordingChannel()
    d = NotificationDispatcher([ch])
    d.notify("order_placed", "ok", secret="topsecret", symbol="XAUUSD")
    fields = d.recent()[0]["fields"]
    assert fields["secret"] == "***REDACTED***"
    assert fields["symbol"] == "XAUUSD"


def test_failing_channel_does_not_break_dispatch():
    class Boom:
        name = "boom"
        def send(self, *a, **k):
            raise RuntimeError("down")

    ch = RecordingChannel()
    d = NotificationDispatcher([Boom(), ch])
    assert d.notify("order_placed", "still works") == 1
