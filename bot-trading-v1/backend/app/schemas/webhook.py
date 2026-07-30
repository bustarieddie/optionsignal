"""Wire-format webhook schema (pydantic v2). Used by the FastAPI layer only.

The tested core (validation/risk/sizing) works on plain dicts so it stays
dependency-light; this module gives the HTTP layer strict type coercion and a
422 on malformed payloads (rulebook §18).
"""
from __future__ import annotations

from datetime import datetime
from typing import Literal, Optional

from pydantic import BaseModel, Field, field_validator


class TradingViewWebhook(BaseModel):
    version: str = "1.0"
    strategy_id: str
    signal_id: str = Field(min_length=1)
    timestamp: datetime
    timezone: str = "Asia/Kuching"
    symbol: str = Field(min_length=1)
    broker_symbol: Optional[str] = None
    timeframe: str = "5"
    side: Literal["BUY", "SELL"]
    entry_type: Literal["BREAKOUT", "RETEST", "STOP"] = "RETEST"
    entry_price: float
    stop_loss: float
    take_profit: float
    risk_reward: float = 2.0
    risk_percent: float = 0.5
    atr_5m: float
    spread: Optional[float] = None
    direction_4h: Literal["BULLISH", "BEARISH", "NEUTRAL"]
    trend_1h: Literal["BULLISH", "BEARISH", "NEUTRAL"]
    pullback_15m: bool
    trigger_5m: str
    setup_expiry: datetime
    bar_time: datetime
    secret: Optional[str] = None

    @field_validator("side", "entry_type", "direction_4h", "trend_1h", mode="before")
    @classmethod
    def _upper(cls, v):
        return v.upper() if isinstance(v, str) else v

    def to_core_dict(self) -> dict:
        """Convert to the plain dict the core validation engine consumes."""
        d = self.model_dump()
        d["timestamp"] = self.timestamp.isoformat()
        d["setup_expiry"] = self.setup_expiry.isoformat()
        d["bar_time"] = self.bar_time.isoformat()
        return d
