from app.brokers.mock import MockBroker
from app.brokers.paper import PaperBroker
from app.schemas.domain import OrderIntent, Side
from app.services.executor import execute
from tests.conftest import make_signal


def _paper():
    return PaperBroker(
        starting_equity=10_000,
        prices={"XAUUSD": 2400.0},
        spread={"XAUUSD": 0.20},
        tick_size={"XAUUSD": 0.01},
        slippage_price=0.05,
    )


def test_paper_market_order_fills_with_spread_and_slippage():
    b = _paper()
    b.connect()
    r = b.place_market_order("XAUUSD", Side.BUY, 0.1, client_order_id="x")
    assert r.ok
    # buy fills at ask (mid + spread/2) + slippage = 2400 + 0.10 + 0.05
    assert round(r.fill_price, 2) == 2400.15


def test_paper_close_realizes_pnl():
    b = _paper()
    b.connect()
    r = b.place_market_order("XAUUSD", Side.BUY, 1.0, client_order_id="x")
    b.set_price("XAUUSD", 2410.0)
    close = b.close_position(r.order_id)
    assert close.ok
    assert b.get_account().equity > 10_000  # profit realized


def test_paper_disconnected_rejects():
    b = _paper()  # not connected
    r = b.place_market_order("XAUUSD", Side.BUY, 0.1, client_order_id="x")
    assert not r.ok and r.reason == "not_connected"


def test_executor_places_entry_and_protection():
    b = MockBroker(equity=10_000, price=2400)
    intent = OrderIntent(signal=make_signal(), lots=0.1, open_risk_percent=0.4)
    res = execute(intent, b)
    assert res.ok and res.protected
    pos = b.get_open_positions()[0]
    assert pos.stop_loss is not None


def test_executor_emergency_closes_when_protection_fails():
    b = MockBroker(equity=10_000, price=2400)
    b.fail_set_protection = True
    intent = OrderIntent(signal=make_signal(), lots=0.1, open_risk_percent=0.4)
    alerts = []
    res = execute(intent, b, notifier=alerts.append)
    assert not res.ok
    assert res.emergency_closed is True
    assert res.paused_symbol == "XAUUSD"
    assert b.get_open_positions() == []          # unprotected position was closed
    assert any("EMERGENCY" in a for a in alerts)


def test_executor_emergency_on_orphan_protection():
    # set_protection returns ok but SL never actually sticks -> still emergency
    b = MockBroker(equity=10_000, price=2400)
    b.orphan_protection = True
    intent = OrderIntent(signal=make_signal(), lots=0.1, open_risk_percent=0.4)
    res = execute(intent, b)
    assert not res.ok and res.emergency_closed is True
