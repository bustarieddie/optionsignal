"""Shared test fixtures/builders for the BOT TRADING v1.0 core."""
from __future__ import annotations

import os
import sys
from datetime import datetime, timedelta, timezone

import pytest

# Make `app` importable when running pytest from the backend/ dir.
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from app.schemas.domain import RiskLimits, RiskState, Side, Signal, SymbolSpec  # noqa: E402


@pytest.fixture
def xauusd_spec() -> SymbolSpec:
    return SymbolSpec(
        symbol="XAUUSD",
        contract_size=100,
        tick_size=0.01,
        tick_value=1.0,      # $1 per tick per 1.0 lot
        min_lot=0.01,
        max_lot=20.0,
        lot_step=0.01,
        est_commission_per_unit=0.0,
        slippage_allowance=0.0,
        min_stop_distance=0.50,
        max_stop_atr=3.0,
        max_spread=0.35,
        min_atr=0.30,
        max_atr=12.0,
        max_trades_per_day=3,
        correlation_group=None,
        quote_currency="USD",
    )


@pytest.fixture
def nas_spec() -> SymbolSpec:
    return SymbolSpec(
        symbol="NAS100", contract_size=1, tick_size=0.1, tick_value=0.1,
        min_lot=0.1, max_lot=50.0, lot_step=0.1, max_spread=2.5,
        max_trades_per_day=3, correlation_group="indices",
    )


@pytest.fixture
def limits() -> RiskLimits:
    return RiskLimits()


@pytest.fixture
def state() -> RiskState:
    return RiskState(equity=10_000.0)


def make_signal(
    *,
    symbol: str = "XAUUSD",
    side: Side = Side.BUY,
    entry: float = 2400.0,
    stop: float = 2396.0,
    atr5: float = 2.0,
    spread: float | None = 0.10,
    expiry_minutes: int = 60,
    bar_time: datetime | None = None,
    group: str | None = None,
) -> Signal:
    now = datetime.now(timezone.utc)
    tp = entry + (entry - stop) * 2 if side is Side.BUY else entry - (stop - entry) * 2
    return Signal(
        signal_id=f"{symbol}-5-{int(now.timestamp())}-{side.value}",
        symbol=symbol,
        broker_symbol=symbol,
        side=side,
        timeframe="5",
        entry_type="RETEST",
        entry_price=entry,
        stop_loss=stop,
        take_profit=tp,
        atr_5m=atr5,
        reward_risk=2.0,
        risk_percent=0.5,
        direction_4h="BULLISH" if side is Side.BUY else "BEARISH",
        trend_1h="BULLISH" if side is Side.BUY else "BEARISH",
        pullback_15m=True,
        trigger_5m="BULLISH_BOS" if side is Side.BUY else "BEARISH_BOS",
        bar_time=bar_time or now,
        setup_expiry=now + timedelta(minutes=expiry_minutes),
        spread=spread,
    )
