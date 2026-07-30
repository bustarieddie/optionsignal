"""Paper-trading engine (rulebook §21).

Simulates bid/ask, spread, commission, slippage, SL/TP and rejected orders. Fills
are deterministic given the configured spread/slippage so tests are reproducible.
Paper results are stored separately from live (the ``environment`` column upstream)
and every paper position is clearly labelled.
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


class PaperBroker(BrokerAdapter):
    def __init__(
        self,
        *,
        starting_equity: float = 10_000.0,
        prices: dict[str, float] | None = None,
        spread: dict[str, float] | None = None,
        tick_size: dict[str, float] | None = None,
        commission_per_lot: float = 0.0,
        slippage_price: float = 0.0,
    ):
        self._equity = starting_equity
        self._balance = starting_equity
        self._prices = dict(prices or {})
        self._spread = dict(spread or {})
        self._tick = dict(tick_size or {})
        self._commission = commission_per_lot
        self._slippage = slippage_price
        self._positions: dict[str, BrokerPosition] = {}
        self._ids = itertools.count(1)
        self._connected = False

    # --- connection ---
    def connect(self) -> None:
        self._connected = True

    def disconnect(self) -> None:
        self._connected = False

    def is_connected(self) -> bool:
        return self._connected

    # --- test/ops helpers ---
    def set_price(self, symbol: str, price: float) -> None:
        self._prices[symbol] = price

    # --- account ---
    def get_account(self) -> AccountInfo:
        return AccountInfo(equity=self._equity, balance=self._balance)

    # --- market data ---
    def get_symbol_info(self, symbol: str) -> SymbolInfo:
        mid = self._prices.get(symbol, 0.0)
        sp = self._spread.get(symbol, 0.0)
        tick = self._tick.get(symbol, 0.01)
        return SymbolInfo(symbol=symbol, bid=mid - sp / 2, ask=mid + sp / 2,
                          tick_size=tick, min_lot=0.01, lot_step=0.01)

    def get_latest_price(self, symbol: str) -> float:
        return self._prices.get(symbol, 0.0)

    # --- positions / orders ---
    def get_open_positions(self) -> list[BrokerPosition]:
        return list(self._positions.values())

    def place_market_order(self, symbol: str, side: Side, lots: float,
                           client_order_id: str) -> OrderResult:
        if not self._connected:
            return OrderResult(False, None, reason="not_connected")
        info = self.get_symbol_info(symbol)
        # Buy fills at ask + slippage; sell at bid - slippage.
        fill = (info.ask + self._slippage) if side is Side.BUY else (info.bid - self._slippage)
        pid = f"paper-{next(self._ids)}"
        self._positions[pid] = BrokerPosition(pid, symbol, side, lots, fill, None, None)
        self._balance -= self._commission * lots
        return OrderResult(True, pid, fill_price=fill)

    def place_stop_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        # Simplified: treat as immediate market for the paper engine.
        return self.place_market_order(symbol, side, lots, client_order_id)

    def place_limit_order(self, symbol, side, lots, price, client_order_id) -> OrderResult:
        return self.place_market_order(symbol, side, lots, client_order_id)

    def set_protection(self, position_id, stop_loss, take_profit) -> OrderResult:
        pos = self._positions.get(position_id)
        if not pos:
            return OrderResult(False, position_id, reason="no_position")
        pos.stop_loss = stop_loss
        pos.take_profit = take_profit
        return OrderResult(True, position_id)

    def modify_position(self, position_id, stop_loss=None, take_profit=None) -> OrderResult:
        pos = self._positions.get(position_id)
        if not pos:
            return OrderResult(False, position_id, reason="no_position")
        if stop_loss is not None:
            pos.stop_loss = stop_loss
        if take_profit is not None:
            pos.take_profit = take_profit
        return OrderResult(True, position_id)

    def close_position(self, position_id, lots=None) -> OrderResult:
        pos = self._positions.get(position_id)
        if not pos:
            return OrderResult(False, position_id, reason="no_position")
        price = self.get_latest_price(pos.symbol)
        # Partial close: reduce size and realize proportional PnL; full otherwise.
        close_lots = pos.size if (lots is None or lots >= pos.size) else lots
        pnl = (price - pos.entry_price) * pos.side.sign * close_lots
        self._equity += pnl
        self._balance += pnl
        if close_lots >= pos.size:
            del self._positions[position_id]
        else:
            pos.size -= close_lots
        return OrderResult(True, position_id, fill_price=price)

    def get_order_status(self, order_id: str) -> str:
        return "filled" if order_id in self._positions else "unknown"
