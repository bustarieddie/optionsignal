"""Deterministic mock broker for tests (rulebook §20, §29, §31).

Lets a test script control connectivity and force failures (e.g. make
``set_protection`` fail to exercise the emergency stop-placement policy).
"""
from __future__ import annotations

import itertools

from app.brokers.base import (
    AccountInfo,
    BrokerAdapter,
    BrokerPosition,
    OrderResult,
    SymbolInfo,
)
from app.schemas.domain import Side


class MockBroker(BrokerAdapter):
    def __init__(self, *, equity: float = 10_000.0, price: float = 100.0):
        self._equity = equity
        self._price = price
        self._connected = True
        self._positions: dict[str, BrokerPosition] = {}
        self._ids = itertools.count(1)
        # Failure switches for tests:
        self.fail_market_order = False
        self.fail_set_protection = False
        self.orphan_protection = False   # position exists but SL never sticks

    def connect(self) -> None:
        self._connected = True

    def disconnect(self) -> None:
        self._connected = False

    def is_connected(self) -> bool:
        return self._connected

    def get_account(self) -> AccountInfo:
        return AccountInfo(self._equity, self._equity)

    def get_symbol_info(self, symbol: str) -> SymbolInfo:
        return SymbolInfo(symbol, self._price, self._price, 0.01, 0.01, 0.01, stop_level=0.0)

    def get_latest_price(self, symbol: str) -> float:
        return self._price

    def get_open_positions(self) -> list[BrokerPosition]:
        return list(self._positions.values())

    def place_market_order(self, symbol, side: Side, lots, client_order_id) -> OrderResult:
        if self.fail_market_order or not self._connected:
            return OrderResult(False, None, reason="mock_fail")
        pid = f"mock-{next(self._ids)}"
        self._positions[pid] = BrokerPosition(pid, symbol, side, lots, self._price, None, None)
        return OrderResult(True, pid, fill_price=self._price)

    def place_stop_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        return self.place_market_order(symbol, side, lots, client_order_id)

    def place_limit_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        return self.place_market_order(symbol, side, lots, client_order_id)

    def set_protection(self, position_id, stop_loss, take_profit) -> OrderResult:
        if self.fail_set_protection:
            return OrderResult(False, position_id, reason="mock_protection_fail")
        pos = self._positions.get(position_id)
        if not pos:
            return OrderResult(False, position_id, reason="no_position")
        if not self.orphan_protection:
            pos.stop_loss = stop_loss
            pos.take_profit = take_profit
        return OrderResult(True, position_id)

    def modify_position(self, position_id, stop_loss=None, take_profit=None) -> OrderResult:
        return self.set_protection(position_id, stop_loss, take_profit)

    def close_position(self, position_id, lots=None) -> OrderResult:
        if self._positions.pop(position_id, None) is None:
            return OrderResult(False, position_id, reason="no_position")
        return OrderResult(True, position_id, fill_price=self._price)

    def get_order_status(self, order_id: str) -> str:
        return "filled" if order_id in self._positions else "unknown"
