"""Risk Management Engine (rulebook §B.5, §B.8, §B.9).

The single authority on "may we trade, and how big?". Pure/deterministic given
the RiskState snapshot. It never calls the broker and never invents broker limits.

Order of checks matters: cheap portfolio gates first, sizing last (sizing is the
only step that can still reject on min-lot / spec problems).
"""
from __future__ import annotations

from app.core import reject_codes as rc
from app.schemas.domain import (
    OrderIntent,
    RiskDecision,
    RiskLimits,
    RiskState,
    Signal,
    SymbolSpec,
)
from app.services.position_sizing import calculate_position_size


def _reject(reason: str) -> RiskDecision:
    return RiskDecision(approved=False, reason=reason)


def evaluate(
    *,
    signal: Signal,
    spec: SymbolSpec,
    limits: RiskLimits,
    state: RiskState,
    live_enabled: bool,
    environment: str,
    fx_to_account: float = 1.0,
) -> RiskDecision:
    """Return APPROVE(order_intent) or REJECT(reason)."""

    # R-11: live gate. Paper/backtest always allowed to proceed here.
    if environment == "live" and not live_enabled:
        return _reject(rc.REJECT_LIVE_TRADING_DISABLED)

    # R-9: one new signal per candle (per symbol+timeframe+bar_time).
    if limits.one_signal_per_candle:
        key = (signal.symbol, signal.timeframe, signal.bar_time)
        if key in state.seen_candles:
            return _reject(rc.REJECT_DUPLICATE_CANDLE)

    # R-8: one open trade per symbol.
    if limits.one_trade_per_symbol and state.has_open(signal.symbol):
        return _reject(rc.REJECT_SYMBOL_ALREADY_OPEN)

    # R-3 / R-4 / R-5: trade & loss streak caps (per day).
    if state.trades_today >= limits.max_trades_per_day:
        return _reject(rc.REJECT_MAX_TRADES)
    if state.trades_today >= spec.max_trades_per_day:  # per-symbol cap (§16)
        return _reject(rc.REJECT_MAX_TRADES)
    if state.losing_trades_today >= limits.max_losing_trades_per_day:
        return _reject(rc.REJECT_MAX_DAILY_LOSSES)
    if state.consecutive_losses >= limits.max_consecutive_losses:
        return _reject(rc.REJECT_MAX_CONSEC_LOSSES)

    # R-6 / R-7: daily & weekly loss limits (loss stored as negative pnl).
    daily_loss_pct = -state.realized_pnl_today / state.equity * 100.0 if state.equity else 0.0
    if daily_loss_pct >= limits.max_daily_loss_percent:
        return _reject(rc.REJECT_DAILY_LOSS_LIMIT)
    weekly_loss_pct = -state.realized_pnl_week / state.equity * 100.0 if state.equity else 0.0
    if weekly_loss_pct >= limits.max_weekly_loss_percent:
        return _reject(rc.REJECT_WEEKLY_LOSS_LIMIT)

    # §B.5: stop distance bounds (min from spec/broker, max from ATR band).
    if spec.min_stop_distance and signal.stop_distance < spec.min_stop_distance:
        return _reject(rc.REJECT_STOP_TOO_TIGHT)
    if signal.atr_5m > 0 and signal.stop_distance > limits.max_stop_atr * signal.atr_5m:
        return _reject(rc.REJECT_STOP_TOO_WIDE)

    # F-SPREAD: spread sanity (per-symbol max).
    if signal.spread is not None and signal.spread > spec.max_spread:
        return _reject(rc.REJECT_SPREAD_HIGH)

    # Sizing (§B.7) — may still reject on min-lot / unreliable inputs.
    sizing = calculate_position_size(
        equity=state.equity,
        risk_percent=limits.risk_per_trade_percent,
        risk_hard_max_percent=limits.risk_hard_max_percent,
        stop_distance=signal.stop_distance,
        spec=spec,
        fx_to_account=fx_to_account,
    )
    if not sizing.ok:
        return _reject(sizing.reason or rc.REJECT_SIZING_UNRELIABLE)

    # R-2: total open risk after adding this trade.
    if state.open_risk_percent() + sizing.open_risk_percent > limits.max_open_risk_percent:
        return _reject(rc.REJECT_MAX_OPEN_RISK)

    # R-10: correlated index-group open risk.
    if spec.correlation_group:
        group_risk = state.group_open_risk_percent(spec.correlation_group)
        if group_risk + sizing.open_risk_percent > limits.max_index_group_risk_percent:
            return _reject(rc.REJECT_CORRELATED_EXPOSURE)

    intent = OrderIntent(
        signal=signal,
        lots=sizing.lots,
        open_risk_percent=sizing.open_risk_percent,
    )
    return RiskDecision(approved=True, reason=None, order_intent=intent)
