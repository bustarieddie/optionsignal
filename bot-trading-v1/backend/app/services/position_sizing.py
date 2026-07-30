"""Deterministic position sizing (rulebook §B.7 / §10).

Pure function of its inputs — no globals, no I/O — so it is fully unit-testable.

    RiskAmount   = equity * risk_pct/100
    LossPerUnit  = stop_distance_price / tick_size * tick_value * fx   (per 1.0 lot)
    RawSize      = RiskAmount / (LossPerUnit + commission + slippage_cost)
    Size         = floor(RawSize / lot_step) * lot_step

Fixed lot is never the default. If any spec input is missing/zero the trade is
rejected as unreliable rather than guessed.
"""
from __future__ import annotations

import math

from app.core import reject_codes as rc
from app.schemas.domain import SizingResult, SymbolSpec


def _floor_to_step(value: float, step: float) -> float:
    if step <= 0:
        return value
    # avoid FP drift: work in integer multiples of step
    return math.floor(round(value / step, 9)) * step


def calculate_position_size(
    *,
    equity: float,
    risk_percent: float,
    risk_hard_max_percent: float,
    stop_distance: float,
    spec: SymbolSpec,
    fx_to_account: float = 1.0,
) -> SizingResult:
    """Return lots to trade and the resulting open-risk % of equity."""
    # Clamp risk to the configured hard maximum (never above).
    risk_percent = min(risk_percent, risk_hard_max_percent)

    if equity <= 0 or risk_percent <= 0:
        return SizingResult(0.0, 0.0, 0.0, False, rc.REJECT_SIZING_UNRELIABLE)
    if stop_distance <= 0:
        return SizingResult(0.0, 0.0, 0.0, False, rc.REJECT_STOP_TOO_TIGHT)
    if spec.tick_size <= 0 or spec.tick_value <= 0 or spec.lot_step <= 0 or fx_to_account <= 0:
        return SizingResult(0.0, 0.0, 0.0, False, rc.REJECT_SIZING_UNRELIABLE)

    risk_amount = equity * (risk_percent / 100.0)

    # Monetary loss per 1.0 lot if stopped out, expressed in the account currency.
    ticks = stop_distance / spec.tick_size
    loss_per_unit = ticks * spec.tick_value * fx_to_account
    cost_per_unit = loss_per_unit + spec.est_commission_per_unit + spec.slippage_allowance
    if cost_per_unit <= 0:
        return SizingResult(0.0, 0.0, 0.0, False, rc.REJECT_SIZING_UNRELIABLE)

    raw_lots = risk_amount / cost_per_unit
    lots = _floor_to_step(raw_lots, spec.lot_step)
    lots = min(lots, spec.max_lot)

    if lots < spec.min_lot or lots <= 0:
        return SizingResult(lots, 0.0, loss_per_unit, False, rc.REJECT_POSITION_TOO_SMALL)

    # Actual open risk after rounding down (always <= requested).
    actual_risk_amount = lots * cost_per_unit
    open_risk_percent = actual_risk_amount / equity * 100.0
    return SizingResult(lots, open_risk_percent, loss_per_unit, True, None)
