"""Concrete LIVE broker adapter — OANDA v20 REST API.

Implemented against OANDA's PUBLICLY DOCUMENTED v20 REST API
(https://developer.oanda.com/rest-live-v20/introduction/). We do not invent
behaviour; every call below maps to a documented endpoint, and broker-specific
assumptions are marked "ASSUMPTION".

Endpoints used:
  • GET  /v3/accounts/{id}/summary                      → equity (NAV) / balance
  • GET  /v3/accounts/{id}/pricing?instruments=...      → bid/ask
  • GET  /v3/accounts/{id}/openTrades                   → open trades (+ SL state)
  • POST /v3/accounts/{id}/orders  {MARKET}             → open a trade
  • POST /v3/accounts/{id}/orders  {STOP_LOSS/TAKE_PROFIT, tradeID} → protection
  • PUT  /v3/accounts/{id}/trades/{tradeID}/close       → close a trade

SAFETY: this class is selected ONLY when env=live AND the LIVE_* gates pass AND
BROKER_KIND=oanda (see core/runtime._build_broker). It requires `httpx`, imported
lazily so the paper/test path never needs it.

The interface places the entry first and protection second (executor step). If
protection fails, the executor's emergency policy closes the unprotected trade.
ASSUMPTION: OANDA STOP_LOSS orders referencing a tradeID are accepted post-fill;
this is documented behaviour. For maximum safety you may instead attach
stopLossOnFill at entry — see the note in place_market_order.
"""
from __future__ import annotations

from app.brokers.base import (
    AccountInfo,
    BrokerAdapter,
    BrokerPosition,
    OrderResult,
    SymbolInfo,
)
from app.schemas.domain import Side

# Canonical → OANDA instrument. Overridable if an OANDA name appears in the
# configured symbol_mapping aliases.
DEFAULT_INSTRUMENTS = {
    "XAUUSD": "XAU_USD",
    "NAS100": "NAS100_USD",
    "US30": "US30_USD",
    "SPX500": "SPX500_USD",
}


# --------------------------------------------------------------------------- #
# Pure helpers (no network) — unit-tested directly.
# --------------------------------------------------------------------------- #
def oanda_instrument(symbol: str, mapping: dict[str, list[str]] | None = None) -> str:
    """Return the OANDA instrument name for a canonical symbol."""
    if mapping:
        for alias in mapping.get(symbol, []):
            if "_" in alias and alias.replace("_", "").isalnum():
                return alias
    return DEFAULT_INSTRUMENTS.get(symbol, symbol)


def units_from_lots(lots: float, side: Side, units_per_lot: float) -> int:
    """OANDA uses signed integer units: +long, -short."""
    units = int(round(lots * units_per_lot))
    return units if side is Side.BUY else -units


def market_order_body(instrument: str, units: int, client_id: str,
                      sl_price: float | None = None, tp_price: float | None = None) -> dict:
    order: dict = {
        "type": "MARKET",
        "instrument": instrument,
        "units": str(units),
        "timeInForce": "FOK",
        "positionFill": "DEFAULT",
        "clientExtensions": {"id": client_id},
    }
    # Optional atomic protection at fill (safest). Left off by default because the
    # executor places protection as an explicit, verifiable second step.
    if sl_price is not None:
        order["stopLossOnFill"] = {"price": f"{sl_price}"}
    if tp_price is not None:
        order["takeProfitOnFill"] = {"price": f"{tp_price}"}
    return {"order": order}


def stop_loss_body(trade_id: str, price: float) -> dict:
    return {"order": {"type": "STOP_LOSS", "tradeID": trade_id, "price": f"{price}"}}


def take_profit_body(trade_id: str, price: float) -> dict:
    return {"order": {"type": "TAKE_PROFIT", "tradeID": trade_id, "price": f"{price}"}}


def parse_account_summary(payload: dict) -> AccountInfo:
    acct = payload.get("account", {})
    return AccountInfo(
        equity=float(acct.get("NAV", acct.get("balance", 0.0))),
        balance=float(acct.get("balance", 0.0)),
        currency=acct.get("currency", "USD"),
    )


def parse_pricing(payload: dict, instrument: str) -> tuple[float, float]:
    """Return (bid, ask) for an instrument from a /pricing response."""
    for p in payload.get("prices", []):
        if p.get("instrument") == instrument:
            bid = float(p["bids"][0]["price"])
            ask = float(p["asks"][0]["price"])
            return bid, ask
    raise KeyError(f"no price for {instrument}")


def parse_order_fill(payload: dict) -> OrderResult:
    """Parse a create-order response into an OrderResult (position_id = tradeID)."""
    fill = payload.get("orderFillTransaction")
    if fill and fill.get("tradeOpened"):
        return OrderResult(
            ok=True,
            order_id=fill["tradeOpened"]["tradeID"],
            fill_price=float(fill.get("price", 0.0)),
        )
    if payload.get("orderCancelTransaction"):
        return OrderResult(False, None, reason=payload["orderCancelTransaction"].get("reason", "cancelled"))
    return OrderResult(False, None, reason="no_fill")


def parse_open_trades(payload: dict, instrument_to_symbol: dict[str, str]) -> list[BrokerPosition]:
    out: list[BrokerPosition] = []
    for t in payload.get("trades", []):
        units = float(t.get("currentUnits", 0))
        side = Side.BUY if units >= 0 else Side.SELL
        sl = t.get("stopLossOrder", {}).get("price") if t.get("stopLossOrder") else None
        tp = t.get("takeProfitOrder", {}).get("price") if t.get("takeProfitOrder") else None
        inst = t.get("instrument", "")
        out.append(BrokerPosition(
            position_id=str(t.get("id")),
            symbol=instrument_to_symbol.get(inst, inst),
            side=side,
            size=abs(units),
            entry_price=float(t.get("price", 0.0)),
            stop_loss=float(sl) if sl is not None else None,
            take_profit=float(tp) if tp is not None else None,
        ))
    return out


# --------------------------------------------------------------------------- #
# Networked adapter.
# --------------------------------------------------------------------------- #
class OandaBroker(BrokerAdapter):
    def __init__(self, *, api_token: str, account_id: str, practice: bool = True,
                 symbol_mapping: dict | None = None, units_per_lot: dict | None = None,
                 timeout: float = 10.0, client=None):
        self._token = api_token
        self._account = account_id
        self._base = ("https://api-fxpractice.oanda.com" if practice
                      else "https://api-fxtrade.oanda.com")
        self._mapping = symbol_mapping or {}
        self._units_per_lot = units_per_lot or {}
        self._timeout = timeout
        self._client = client  # injectable for tests
        self._connected = False

    # --- low-level HTTP (lazy httpx) ---
    def _http(self):
        if self._client is not None:
            return self._client
        import httpx  # lazy: only needed for real live trading
        self._client = httpx.Client(
            base_url=self._base, timeout=self._timeout,
            headers={"Authorization": f"Bearer {self._token}",
                     "Content-Type": "application/json"},
        )
        return self._client

    def _get(self, path: str, params: dict | None = None) -> dict:
        r = self._http().get(path, params=params)
        r.raise_for_status()
        return r.json()

    def _post(self, path: str, body: dict) -> dict:
        r = self._http().post(path, json=body)
        # OANDA returns 201 on order create, 400/404 with a body on rejection.
        if r.status_code >= 500:
            r.raise_for_status()
        return r.json()

    def _put(self, path: str, body: dict) -> dict:
        r = self._http().put(path, json=body)
        if r.status_code >= 500:
            r.raise_for_status()
        return r.json()

    def _inst(self, symbol: str) -> str:
        return oanda_instrument(symbol, self._mapping)

    def _inst_to_symbol(self) -> dict[str, str]:
        return {self._inst(s): s for s in self._units_per_lot} or \
               {v: k for k, v in DEFAULT_INSTRUMENTS.items()}

    # --- connection ---
    def connect(self) -> None:
        self.get_account()   # will raise if credentials are bad
        self._connected = True

    def disconnect(self) -> None:
        if self._client is not None and hasattr(self._client, "close"):
            self._client.close()
        self._connected = False

    def is_connected(self) -> bool:
        return self._connected

    # --- account ---
    def get_account(self) -> AccountInfo:
        return parse_account_summary(self._get(f"/v3/accounts/{self._account}/summary"))

    # --- market data ---
    def get_symbol_info(self, symbol: str) -> SymbolInfo:
        inst = self._inst(symbol)
        bid, ask = parse_pricing(
            self._get(f"/v3/accounts/{self._account}/pricing", {"instruments": inst}), inst)
        return SymbolInfo(symbol=symbol, bid=bid, ask=ask, tick_size=0.0,
                          min_lot=0.0, lot_step=0.0)

    def get_latest_price(self, symbol: str) -> float:
        info = self.get_symbol_info(symbol)
        return (info.bid + info.ask) / 2

    # --- positions / orders ---
    def get_open_positions(self) -> list[BrokerPosition]:
        payload = self._get(f"/v3/accounts/{self._account}/openTrades")
        return parse_open_trades(payload, self._inst_to_symbol())

    def place_market_order(self, symbol, side: Side, lots, client_order_id) -> OrderResult:
        inst = self._inst(symbol)
        units = units_from_lots(lots, side, self._units_per_lot.get(symbol, 1))
        body = market_order_body(inst, units, client_order_id)
        return parse_order_fill(self._post(f"/v3/accounts/{self._account}/orders", body))

    def place_stop_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        inst = self._inst(symbol)
        units = units_from_lots(lots, side, self._units_per_lot.get(symbol, 1))
        body = {"order": {"type": "STOP", "instrument": inst, "units": str(units),
                          "price": f"{price}", "timeInForce": "GTC",
                          "clientExtensions": {"id": client_order_id}}}
        return parse_order_fill(self._post(f"/v3/accounts/{self._account}/orders", body))

    def place_limit_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        inst = self._inst(symbol)
        units = units_from_lots(lots, side, self._units_per_lot.get(symbol, 1))
        body = {"order": {"type": "LIMIT", "instrument": inst, "units": str(units),
                          "price": f"{price}", "timeInForce": "GTC",
                          "clientExtensions": {"id": client_order_id}}}
        return parse_order_fill(self._post(f"/v3/accounts/{self._account}/orders", body))

    def set_protection(self, position_id, stop_loss, take_profit) -> OrderResult:
        # Create a STOP_LOSS (and TAKE_PROFIT) order referencing the open trade.
        if stop_loss is not None:
            res = self._post(f"/v3/accounts/{self._account}/orders",
                             stop_loss_body(position_id, stop_loss))
            if not res.get("orderCreateTransaction"):
                return OrderResult(False, position_id, reason="sl_rejected")
        if take_profit is not None:
            self._post(f"/v3/accounts/{self._account}/orders",
                       take_profit_body(position_id, take_profit))
        return OrderResult(True, position_id)

    def modify_position(self, position_id, stop_loss=None, take_profit=None) -> OrderResult:
        return self.set_protection(position_id, stop_loss, take_profit)

    def close_position(self, position_id, lots=None) -> OrderResult:
        res = self._put(f"/v3/accounts/{self._account}/trades/{position_id}/close",
                        {"units": "ALL"})
        ok = bool(res.get("orderFillTransaction"))
        return OrderResult(ok, position_id, reason=None if ok else "close_failed")

    def get_order_status(self, order_id: str) -> str:
        try:
            res = self._get(f"/v3/accounts/{self._account}/orders/{order_id}")
            return res.get("order", {}).get("state", "unknown").lower()
        except Exception:
            return "unknown"
