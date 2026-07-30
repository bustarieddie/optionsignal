"""Open-position management engine (rulebook §9 / X-1..X-5).

A deterministic PLANNER: given a managed position, the current price/ATR/bar-count
and the exit config, it returns the management actions to take. It never touches
the broker itself — ``apply_actions`` does that — so the decision logic is pure
and fully unit-testable.

Exit modes (config `exit_mode`):
  • fixed_rr       — TP at reward_risk R (placed at entry; nothing to manage here)
  • partial        — close p1_pct at p1_r R, move stop to breakeven after be_r R,
                     remainder runs to reward_risk R
  • atr_trail      — after trail_activate_r R, trail stop by trail_atr_mult × ATR
  • structure_exit — close remainder on an opposite 5M CHoCH / HTF invalidation
                     (event-driven — see on_opposite_structure)
Plus time_exit (X-5): review/close after max_trade_bars with no meaningful progress.
"""
from __future__ import annotations

from dataclasses import dataclass, field

from app.schemas.domain import Side


# --------------------------------------------------------------------------- #
# Actions
# --------------------------------------------------------------------------- #
@dataclass(frozen=True)
class MoveStop:
    price: float
    reason: str


@dataclass(frozen=True)
class ClosePartial:
    fraction: float          # 0..1 of the ORIGINAL size
    reason: str


@dataclass(frozen=True)
class CloseFull:
    reason: str


Action = MoveStop | ClosePartial | CloseFull


# --------------------------------------------------------------------------- #
# Config + state
# --------------------------------------------------------------------------- #
@dataclass(frozen=True)
class ExitConfig:
    exit_mode: str = "fixed_rr"
    reward_risk: float = 2.0
    p1_pct: float = 50.0
    p1_r: float = 1.0
    be_r: float = 1.0
    trail_activate_r: float = 1.0
    trail_atr_mult: float = 1.5
    max_trade_bars: int = 48


def exit_config_from(strategy: dict) -> "ExitConfig":
    """Build an ExitConfig from the YAML `strategy` block."""
    return ExitConfig(
        exit_mode=strategy.get("exit_mode", "fixed_rr"),
        reward_risk=strategy.get("reward_risk", 2.0),
        p1_pct=strategy.get("p1_pct", 50.0),
        p1_r=strategy.get("p1_r", 1.0),
        be_r=strategy.get("be_r", 1.0),
        trail_activate_r=strategy.get("trail_activate_r", 1.0),
        trail_atr_mult=strategy.get("trail_atr_mult", 1.5),
        max_trade_bars=strategy.get("max_trade_bars", 48),
    )


@dataclass
class ManagedPosition:
    position_id: str
    side: Side
    entry: float
    initial_stop: float          # stop at entry — defines 1R
    current_stop: float
    size: float                  # original size
    atr: float = 0.0             # 5M ATR at entry — proxy for auto-trailing
    partial_taken: bool = False
    breakeven_done: bool = False
    trail_active: bool = False
    bars_open: int = 0
    peak_r: float = 0.0          # best favorable R reached (for progress/time-exit)

    @property
    def initial_risk(self) -> float:
        return abs(self.entry - self.initial_stop)

    def r_at(self, price: float) -> float:
        if self.initial_risk == 0:
            return 0.0
        return (price - self.entry) * self.side.sign / self.initial_risk


# --------------------------------------------------------------------------- #
# Planner
# --------------------------------------------------------------------------- #
def _favorable(side: Side, new_stop: float, current_stop: float) -> bool:
    """A stop move is only allowed in the risk-reducing direction (never wider)."""
    return new_stop > current_stop if side is Side.BUY else new_stop < current_stop


def plan_management(mp: ManagedPosition, price: float, atr: float, cfg: ExitConfig) -> list[Action]:
    """Return the actions to take now. Mutates `mp`'s flags/peak so repeated calls
    don't re-fire the same action (idempotent per milestone)."""
    actions: list[Action] = []
    r = mp.r_at(price)
    mp.peak_r = max(mp.peak_r, r)

    if cfg.exit_mode == "partial":
        if not mp.partial_taken and r >= cfg.p1_r:
            actions.append(ClosePartial(cfg.p1_pct / 100.0, "partial_take_1R"))
            mp.partial_taken = True
        if not mp.breakeven_done and r >= cfg.be_r:
            if _favorable(mp.side, mp.entry, mp.current_stop):
                actions.append(MoveStop(mp.entry, "breakeven"))
                mp.current_stop = mp.entry
            mp.breakeven_done = True

    elif cfg.exit_mode == "atr_trail":
        if r >= cfg.trail_activate_r and atr > 0:
            mp.trail_active = True
        if mp.trail_active:
            new_stop = price - mp.side.sign * cfg.trail_atr_mult * atr
            if _favorable(mp.side, new_stop, mp.current_stop):
                actions.append(MoveStop(new_stop, "atr_trail"))
                mp.current_stop = new_stop

    # X-5 time exit: open too long without meaningful progress (never reached p1_r).
    if cfg.max_trade_bars and mp.bars_open >= cfg.max_trade_bars and mp.peak_r < cfg.p1_r:
        actions.append(CloseFull("time_exit"))

    return actions


def on_opposite_structure(mp: ManagedPosition) -> list[Action]:
    """X-4 structure exit: an opposite 5M CHoCH / HTF invalidation arrived
    (event-driven, supplied by a new inbound signal). Close the remainder."""
    return [CloseFull("structure_exit")]


# --------------------------------------------------------------------------- #
# Applier — the only part that touches the broker.
# --------------------------------------------------------------------------- #
def apply_actions(broker, mp: ManagedPosition, actions: list[Action], *, notifier=None) -> list[str]:
    """Execute planned actions against the broker adapter. Returns applied labels."""
    applied: list[str] = []
    for a in actions:
        if isinstance(a, MoveStop):
            res = broker.modify_position(mp.position_id, stop_loss=a.price)
            if res.ok:
                applied.append(a.reason)
                if notifier and a.reason == "breakeven":
                    notifier("breakeven", f"{mp.position_id} stop → breakeven {a.price}")
        elif isinstance(a, ClosePartial):
            lots = round(mp.size * a.fraction, 8)
            res = broker.close_position(mp.position_id, lots=lots)
            if res.ok:
                applied.append(a.reason)
                if notifier:
                    notifier("partial_profit", f"{mp.position_id} closed {a.fraction:.0%} ({lots})")
        elif isinstance(a, CloseFull):
            res = broker.close_position(mp.position_id)
            if res.ok:
                applied.append(a.reason)
                if notifier:
                    notifier("stop_hit" if a.reason == "time_exit" else "tp_hit",
                             f"{mp.position_id} closed: {a.reason}")
    return applied
