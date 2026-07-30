"""Template LIVE broker adapter (rulebook §20, §35).

INTENTIONALLY NOT IMPLEMENTED. This is a skeleton you copy per broker (MT5,
cTrader, OANDA, IBKR, ...). We do NOT invent broker API behaviour. Every method
raises NotImplementedError with a note on what the real venue call must do.

Enabling live trading requires, in addition to a real implementation here:
  • LIVE_TRADING_ENABLED=true
  • LIVE_CONFIRMATION_PHRASE=<your configured value>
Until all three are satisfied the executor returns REJECT_LIVE_TRADING_DISABLED.
"""
from __future__ import annotations

from app.brokers.base import AccountInfo, BrokerAdapter, BrokerPosition, OrderResult, SymbolInfo
from app.schemas.domain import Side


class LiveBrokerTemplate(BrokerAdapter):
    def __init__(self, *, credentials: dict):
        # Store an encrypted handle only; never log credentials.
        self._credentials = credentials
        self._connected = False

    def _todo(self, what: str):
        raise NotImplementedError(
            f"LiveBrokerTemplate.{what} must be implemented against your broker's API. "
            "Map canonical symbols via config symbol_mapping; place the protective "
            "stop atomically with (or immediately after) entry; and honour the "
            "broker's stop-level / min-lot constraints."
        )

    def connect(self) -> None: self._todo("connect")
    def disconnect(self) -> None: self._todo("disconnect")
    def is_connected(self) -> bool: return self._connected
    def get_account(self) -> AccountInfo: self._todo("get_account")
    def get_symbol_info(self, symbol: str) -> SymbolInfo: self._todo("get_symbol_info")
    def get_latest_price(self, symbol: str) -> float: self._todo("get_latest_price")
    def get_open_positions(self) -> list[BrokerPosition]: self._todo("get_open_positions")
    def place_market_order(self, symbol, side: Side, lots, client_order_id) -> OrderResult:
        self._todo("place_market_order")
    def place_stop_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        self._todo("place_stop_order")
    def place_limit_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        self._todo("place_limit_order")
    def set_protection(self, position_id, stop_loss, take_profit) -> OrderResult:
        self._todo("set_protection")
    def modify_position(self, position_id, stop_loss=None, take_profit=None) -> OrderResult:
        self._todo("modify_position")
    def close_position(self, position_id, lots=None) -> OrderResult:
        self._todo("close_position")
    def get_order_status(self, order_id: str) -> str: self._todo("get_order_status")
