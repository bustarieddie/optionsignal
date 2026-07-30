"""OANDA v20 adapter tests. Pure helpers + a full flow via an injected fake
HTTP client (no network, no httpx dependency needed)."""
from app.brokers.oanda import (
    OandaBroker,
    market_order_body,
    oanda_instrument,
    parse_account_summary,
    parse_open_trades,
    parse_order_fill,
    parse_pricing,
    stop_loss_body,
    units_from_lots,
)
from app.schemas.domain import OrderIntent, Side
from app.services.executor import execute
from tests.conftest import make_signal


# --- pure helpers ---
def test_instrument_mapping():
    assert oanda_instrument("XAUUSD") == "XAU_USD"
    assert oanda_instrument("NAS100") == "NAS100_USD"
    # config alias override
    assert oanda_instrument("US30", {"US30": ["US30_USD"]}) == "US30_USD"


def test_units_signed_by_side():
    assert units_from_lots(1.0, Side.BUY, 100) == 100
    assert units_from_lots(1.0, Side.SELL, 100) == -100
    assert units_from_lots(0.1, Side.BUY, 100) == 10


def test_market_order_body_shape():
    b = market_order_body("XAU_USD", 100, "sig-1")
    assert b["order"]["type"] == "MARKET"
    assert b["order"]["units"] == "100"
    assert b["order"]["clientExtensions"]["id"] == "sig-1"
    assert "stopLossOnFill" not in b["order"]


def test_stop_loss_body():
    b = stop_loss_body("42", 2396.0)
    assert b["order"] == {"type": "STOP_LOSS", "tradeID": "42", "price": "2396.0"}


def test_parse_account_summary_uses_nav():
    info = parse_account_summary({"account": {"NAV": "10250.5", "balance": "10000", "currency": "USD"}})
    assert info.equity == 10250.5 and info.balance == 10000.0


def test_parse_pricing():
    payload = {"prices": [{"instrument": "XAU_USD",
                           "bids": [{"price": "2399.9"}], "asks": [{"price": "2400.1"}]}]}
    assert parse_pricing(payload, "XAU_USD") == (2399.9, 2400.1)


def test_parse_order_fill_success_and_cancel():
    ok = parse_order_fill({"orderFillTransaction": {"tradeOpened": {"tradeID": "77"}, "price": "2400.2"}})
    assert ok.ok and ok.order_id == "77" and ok.fill_price == 2400.2
    bad = parse_order_fill({"orderCancelTransaction": {"reason": "MARKET_HALTED"}})
    assert not bad.ok and bad.reason == "MARKET_HALTED"


def test_parse_open_trades_reads_stop_state():
    payload = {"trades": [
        {"id": "1", "instrument": "XAU_USD", "currentUnits": "100", "price": "2400",
         "stopLossOrder": {"price": "2396"}},
        {"id": "2", "instrument": "NAS100_USD", "currentUnits": "-10", "price": "20000"},
    ]}
    trades = parse_open_trades(payload, {"XAU_USD": "XAUUSD", "NAS100_USD": "NAS100"})
    assert trades[0].symbol == "XAUUSD" and trades[0].stop_loss == 2396.0
    assert trades[1].side is Side.SELL and trades[1].stop_loss is None


# --- full flow with a fake HTTP client ---
class _Resp:
    def __init__(self, status, payload):
        self.status_code = status
        self._payload = payload

    def json(self):
        return self._payload

    def raise_for_status(self):
        if self.status_code >= 500:
            raise RuntimeError(f"HTTP {self.status_code}")


class FakeOandaClient:
    """Minimal stand-in for httpx.Client that mimics OANDA v20 responses."""

    def __init__(self):
        self.calls = []
        self._trades = {}
        self._next = 1

    def get(self, path, params=None):
        self.calls.append(("GET", path, params))
        if path.endswith("/summary"):
            return _Resp(200, {"account": {"NAV": "10000", "balance": "10000", "currency": "USD"}})
        if path.endswith("/openTrades"):
            return _Resp(200, {"trades": list(self._trades.values())})
        if "/pricing" in path:
            return _Resp(200, {"prices": [{"instrument": params["instruments"],
                                           "bids": [{"price": "2399.9"}], "asks": [{"price": "2400.1"}]}]})
        return _Resp(200, {})

    def post(self, path, json=None):
        self.calls.append(("POST", path, json))
        order = json["order"]
        if order["type"] == "MARKET":
            tid = str(self._next); self._next += 1
            self._trades[tid] = {"id": tid, "instrument": order["instrument"],
                                 "currentUnits": order["units"], "price": "2400.1"}
            return _Resp(201, {"orderFillTransaction": {"tradeOpened": {"tradeID": tid}, "price": "2400.1"}})
        if order["type"] == "STOP_LOSS":
            self._trades[order["tradeID"]]["stopLossOrder"] = {"price": order["price"]}
            return _Resp(201, {"orderCreateTransaction": {"id": "sl"}})
        if order["type"] == "TAKE_PROFIT":
            self._trades[order["tradeID"]]["takeProfitOrder"] = {"price": order["price"]}
            return _Resp(201, {"orderCreateTransaction": {"id": "tp"}})
        return _Resp(400, {"orderRejectTransaction": {}})

    def put(self, path, json=None):
        self.calls.append(("PUT", path, json))
        tid = path.split("/trades/")[1].split("/close")[0]
        self._trades.pop(tid, None)
        return _Resp(200, {"orderFillTransaction": {"id": "close"}})


def _broker():
    return OandaBroker(api_token="t", account_id="acc", practice=True,
                       units_per_lot={"XAUUSD": 100}, client=FakeOandaClient())


def test_oanda_connect_and_equity():
    b = _broker()
    b.connect()
    assert b.is_connected()
    assert b.get_equity() == 10000.0


def test_oanda_executor_full_flow_protected():
    b = _broker()
    b.connect()
    intent = OrderIntent(signal=make_signal(), lots=0.1, open_risk_percent=0.4)
    res = execute(intent, b)
    assert res.ok and res.protected
    pos = b.get_open_positions()[0]
    assert pos.stop_loss is not None      # STOP_LOSS order was created & verified


def test_oanda_close_removes_trade():
    b = _broker()
    b.connect()
    r = b.place_market_order("XAUUSD", Side.BUY, 0.1, client_order_id="x")
    assert b.close_position(r.order_id).ok
    assert b.get_open_positions() == []
