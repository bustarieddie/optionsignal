"""Process-wide runtime wiring (single authoritative instance for risk state).

Builds the broker for the active environment, the risk limits/state, the dedup
store, auth config and the state machine. The FastAPI layer holds one Runtime on
app.state; the tested core never imports this module.
"""
from __future__ import annotations

from dataclasses import dataclass, field

from app.brokers.base import BrokerAdapter
from app.brokers.paper import PaperBroker
from app.core.config import Settings, load_settings
from app.core.idempotency import DedupStore, InMemoryDedupStore, RedisDedupStore
from app.core.security import AdminAuth, AuthConfig
from app.core.states import StateMachine, SystemState
from app.schemas.domain import RiskLimits, RiskState, SymbolSpec


def _spec_from_yaml(symbol: str, raw: dict, mapping: dict) -> SymbolSpec:
    return SymbolSpec(
        symbol=symbol,
        contract_size=raw.get("contract_size", 1),
        tick_size=raw.get("tick_size", 0.01),
        tick_value=raw.get("tick_value", 1.0),
        min_lot=raw.get("min_lot", 0.01),
        max_lot=raw.get("max_lot", 100.0),
        lot_step=raw.get("lot_step", 0.01),
        est_commission_per_unit=raw.get("est_commission_per_unit", 0.0),
        slippage_allowance=raw.get("slippage_allowance", 0.0),
        min_stop_distance=raw.get("min_stop_distance", 0.0),
        max_stop_atr=raw.get("max_stop_atr", 3.0),
        max_spread=raw.get("max_spread", float("inf")),
        min_atr=raw.get("min_atr", 0.0),
        max_atr=raw.get("max_atr", float("inf")),
        max_trades_per_day=raw.get("max_trades_per_day", 3),
        correlation_group=raw.get("correlation_group"),
        quote_currency=raw.get("quote_currency", "USD"),
        broker_names=tuple(mapping.get(symbol, [])),
    )


def _limits_from_yaml(risk: dict) -> RiskLimits:
    return RiskLimits(
        risk_per_trade_percent=risk.get("risk_per_trade_percent", 0.5),
        risk_hard_max_percent=risk.get("risk_hard_max_percent", 1.0),
        max_open_risk_percent=risk.get("max_open_risk_percent", 1.5),
        max_index_group_risk_percent=risk.get("max_index_group_risk_percent", 1.0),
        max_daily_loss_percent=risk.get("max_daily_loss_percent", 2.0),
        max_weekly_loss_percent=risk.get("max_weekly_loss_percent", 5.0),
        max_trades_per_day=risk.get("max_trades_per_day", 3),
        max_losing_trades_per_day=risk.get("max_losing_trades_per_day", 2),
        max_consecutive_losses=risk.get("max_consecutive_losses", 3),
        one_trade_per_symbol=risk.get("one_trade_per_symbol", True),
        one_signal_per_candle=risk.get("one_signal_per_candle", True),
    )


def _build_broker(settings: Settings) -> BrokerAdapter:
    # Live template is NOT auto-selected. Live requires an explicit real adapter.
    prices = {s: 0.0 for s in settings.symbols}
    b = PaperBroker(
        starting_equity=float(settings.system.get("starting_equity", 10_000)),
        prices=prices,
        spread={s: v.get("max_spread", 0.0) * 0.3 for s, v in settings.symbols.items()},
        tick_size={s: v.get("tick_size", 0.01) for s, v in settings.symbols.items()},
    )
    b.connect()
    return b


def _dedup(settings: Settings) -> DedupStore:
    if settings.redis_url:
        try:  # pragma: no cover - optional dependency
            import redis
            return RedisDedupStore(redis.Redis.from_url(settings.redis_url))
        except Exception:
            pass
    return InMemoryDedupStore()


@dataclass
class Runtime:
    settings: Settings
    broker: BrokerAdapter
    limits: RiskLimits
    risk_state: RiskState
    dedup: DedupStore
    auth: AuthConfig
    admin: AdminAuth
    sm: StateMachine
    specs: dict[str, SymbolSpec] = field(default_factory=dict)
    paused_symbols: set = field(default_factory=set)

    def live_enabled(self) -> bool:
        return self.settings.live_ready()


def build_runtime(settings: Settings | None = None) -> Runtime:
    s = settings or load_settings()
    specs = {sym: _spec_from_yaml(sym, raw, s.symbol_mapping) for sym, raw in s.symbols.items()}
    broker = _build_broker(s)
    sm = StateMachine(SystemState.STARTING)
    if s.environment == "live" and not s.live_ready():
        sm.transition(SystemState.LIVE_DISABLED, "live preconditions unmet")
    else:
        sm.transition(SystemState.PAPER_MODE if s.environment != "live" else SystemState.READY,
                      f"env={s.environment}")
        sm.transition(SystemState.READY, "startup complete")
    return Runtime(
        settings=s,
        broker=broker,
        limits=_limits_from_yaml(s.risk),
        risk_state=RiskState(equity=broker.get_equity() or 10_000.0),
        dedup=_dedup(s),
        auth=AuthConfig(webhook_secret=s.webhook_secret, url_token=s.url_token,
                        hmac_required=s.hmac_required, ip_allowlist=s.ip_allowlist),
        admin=AdminAuth(token=s.admin_token or "change-me"),
        sm=sm,
        specs=specs,
    )
