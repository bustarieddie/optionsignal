"""Broker adapter interface (rulebook §20).

The execution boundary. An adapter translates an approved OrderIntent into venue
calls and NOTHING ELSE — it never decides whether or how big to trade. Concrete
adapters: paper (default), mock (tests), live_template (you implement per venue).
"""
from __future__ import annotations

import abc
from dataclasses import dataclass
from datetime import datetime

from app.schemas.domain import Side


@dataclass
class AccountInfo:
    equity: float
    balance: float
    currency: str = "USD"


@dataclass
class SymbolInfo:
    symbol: str
    bid: float
    ask: float
    tick_size: float
    min_lot: float
    lot_step: float
    stop_level: float = 0.0     # broker minimum stop distance (price units)

    @property
    def spread(self) -> float:
        return self.ask - self.bid


@dataclass
class OrderResult:
    ok: bool
    order_id: str | None
    fill_price: float | None = None
    reason: str | None = None


@dataclass
class BrokerPosition:
    position_id: str
    symbol: str
    side: Side
    size: float
    entry_price: float
    stop_loss: float | None
    take_profit: float | None


class BrokerAdapter(abc.ABC):
    """All methods a live venue must expose. Paper/mock implement the same shape."""

    # --- connection ---
    @abc.abstractmethod
    def connect(self) -> None: ...
    @abc.abstractmethod
    def disconnect(self) -> None: ...
    @abc.abstractmethod
    def is_connected(self) -> bool: ...

    # --- account ---
    @abc.abstractmethod
    def get_account(self) -> AccountInfo: ...
    def get_equity(self) -> float:
        return self.get_account().equity
    def get_balance(self) -> float:
        return self.get_account().balance

    # --- market data ---
    @abc.abstractmethod
    def get_symbol_info(self, symbol: str) -> SymbolInfo: ...
    @abc.abstractmethod
    def get_latest_price(self, symbol: str) -> float: ...

    # --- positions / orders ---
    @abc.abstractmethod
    def get_open_positions(self) -> list[BrokerPosition]: ...
    @abc.abstractmethod
    def place_market_order(self, symbol: str, side: Side, lots: float,
                           client_order_id: str) -> OrderResult: ...
    @abc.abstractmethod
    def place_stop_order(self, symbol: str, side: Side, lots: float, price: float,
                         client_order_id: str) -> OrderResult: ...
    @abc.abstractmethod
    def place_limit_order(self, symbol: str, side: Side, lots: float, price: float,
                          client_order_id: str) -> OrderResult: ...
    @abc.abstractmethod
    def set_protection(self, position_id: str, stop_loss: float | None,
                       take_profit: float | None) -> OrderResult: ...
    @abc.abstractmethod
    def modify_position(self, position_id: str, stop_loss: float | None = None,
                        take_profit: float | None = None) -> OrderResult: ...
    @abc.abstractmethod
    def close_position(self, position_id: str, lots: float | None = None) -> OrderResult: ...
    @abc.abstractmethod
    def get_order_status(self, order_id: str) -> str: ...
    def get_trade_history(self) -> list[dict]:
        return []
