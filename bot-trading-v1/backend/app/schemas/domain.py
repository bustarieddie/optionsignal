"""Core domain objects (pure stdlib dataclasses — no external deps).

These are the internal representations passed between the validation, risk,
sizing and execution engines. The wire-format webhook schema lives separately in
``app/schemas/webhook.py`` (pydantic) so the tested core stays dependency-light.
"""
from __future__ import annotations

import enum
from dataclasses import dataclass, field
from datetime import datetime


class Side(str, enum.Enum):
    BUY = "BUY"
    SELL = "SELL"

    @property
    def sign(self) -> int:
        return 1 if self is Side.BUY else -1


@dataclass(frozen=True)
class SymbolSpec:
    """Broker/instrument contract spec (rulebook §B.7 / §16). Broker-specific."""
    symbol: str
    contract_size: float
    tick_size: float
    tick_value: float           # account-ccy value of one tick per 1.0 lot
    min_lot: float
    max_lot: float
    lot_step: float
    est_commission_per_unit: float = 0.0
    slippage_allowance: float = 0.0
    min_stop_distance: float = 0.0
    max_stop_atr: float = 3.0
    max_spread: float = float("inf")
    min_atr: float = 0.0
    max_atr: float = float("inf")
    max_trades_per_day: int = 3
    correlation_group: str | None = None
    quote_currency: str = "USD"
    broker_names: tuple[str, ...] = ()


@dataclass
class Signal:
    """A validated, normalized signal (post Validation Engine)."""
    signal_id: str
    symbol: str
    broker_symbol: str
    side: Side
    timeframe: str
    entry_type: str
    entry_price: float
    stop_loss: float
    take_profit: float
    atr_5m: float
    reward_risk: float
    risk_percent: float
    direction_4h: str
    trend_1h: str
    pullback_15m: bool
    trigger_5m: str
    bar_time: datetime
    setup_expiry: datetime
    spread: float | None = None
    raw: dict = field(default_factory=dict)

    @property
    def stop_distance(self) -> float:
        return abs(self.entry_price - self.stop_loss)


@dataclass(frozen=True)
class RiskLimits:
    risk_per_trade_percent: float = 0.5
    risk_hard_max_percent: float = 1.0
    max_open_risk_percent: float = 1.5
    max_index_group_risk_percent: float = 1.0
    max_daily_loss_percent: float = 2.0
    max_weekly_loss_percent: float = 5.0
    max_trades_per_day: int = 3
    max_losing_trades_per_day: int = 2
    max_consecutive_losses: int = 3
    one_trade_per_symbol: bool = True
    one_signal_per_candle: bool = True
    max_stop_atr: float = 3.0
    signal_dev_atr: float = 0.25


@dataclass
class Position:
    symbol: str
    side: Side
    size: float
    entry_price: float
    stop_loss: float
    open_risk_percent: float
    correlation_group: str | None = None


@dataclass
class RiskState:
    """Live account + day/week counters the risk engine reads (never mutates
    silently — the executor updates it after fills/closes)."""
    equity: float
    trades_today: int = 0
    losing_trades_today: int = 0
    consecutive_losses: int = 0
    realized_pnl_today: float = 0.0
    realized_pnl_week: float = 0.0
    open_positions: list[Position] = field(default_factory=list)
    seen_candles: set = field(default_factory=set)   # {(symbol, timeframe, bar_time)}

    def open_risk_percent(self) -> float:
        return sum(p.open_risk_percent for p in self.open_positions)

    def group_open_risk_percent(self, group: str) -> float:
        return sum(p.open_risk_percent for p in self.open_positions if p.correlation_group == group)

    def has_open(self, symbol: str) -> bool:
        return any(p.symbol == symbol for p in self.open_positions)


@dataclass
class SizingResult:
    lots: float
    open_risk_percent: float
    loss_per_unit: float
    ok: bool
    reason: str | None = None


@dataclass
class OrderIntent:
    signal: Signal
    lots: float
    open_risk_percent: float


@dataclass
class RiskDecision:
    approved: bool
    reason: str | None = None
    order_intent: OrderIntent | None = None
