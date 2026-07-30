"""Configuration loader (rulebook §33; secrets from env only, tunables from YAML).

Kept dependency-light on purpose: env vars via os.environ, YAML via pyyaml. No
secret ever lives in the YAML — WEBHOOK_SECRET, URL_TOKEN, admin/broker creds all
come from the environment. Missing-secret defaults are safe (live stays disabled).
"""
from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

try:
    import yaml
except Exception:  # pragma: no cover - yaml is a declared dep
    yaml = None


def _env_bool(name: str, default: bool = False) -> bool:
    return os.environ.get(name, str(default)).strip().lower() in {"1", "true", "yes", "on"}


@dataclass
class Settings:
    # --- environment / mode ---
    environment: str = field(default_factory=lambda: os.environ.get("ENVIRONMENT", "paper"))
    live_trading_enabled: bool = field(default_factory=lambda: _env_bool("LIVE_TRADING_ENABLED", False))
    live_confirmation_phrase: str = field(default_factory=lambda: os.environ.get("LIVE_CONFIRMATION_PHRASE", ""))
    live_confirmation_expected: str = field(default_factory=lambda: os.environ.get("LIVE_CONFIRMATION_EXPECTED", ""))

    # --- webhook auth ---
    webhook_secret: str = field(default_factory=lambda: os.environ.get("WEBHOOK_SECRET", ""))
    url_token: str = field(default_factory=lambda: os.environ.get("URL_TOKEN", ""))
    hmac_required: bool = field(default_factory=lambda: _env_bool("HMAC_REQUIRED", False))
    ip_allowlist: tuple[str, ...] = field(
        default_factory=lambda: tuple(x for x in os.environ.get("IP_ALLOWLIST", "").split(",") if x)
    )

    # --- admin ---
    admin_token: str = field(default_factory=lambda: os.environ.get("ADMIN_TOKEN", ""))

    # --- infra ---
    database_url: str = field(default_factory=lambda: os.environ.get("DATABASE_URL", "sqlite+pysqlite:///./bot.db"))
    redis_url: str = field(default_factory=lambda: os.environ.get("REDIS_URL", ""))
    signal_dedup_ttl: int = field(default_factory=lambda: int(os.environ.get("SIGNAL_DEDUP_TTL", "3600")))

    # --- news feed (optional; blank => honest 'unavailable') ---
    news_calendar_file: str = field(default_factory=lambda: os.environ.get("NEWS_CALENDAR_FILE", ""))

    # --- background scheduler (opt-in) ---
    scheduler_enabled: bool = field(default_factory=lambda: _env_bool("SCHEDULER_ENABLED", False))
    scheduler_interval: int = field(default_factory=lambda: int(os.environ.get("SCHEDULER_INTERVAL", "15")))

    # --- live broker selection ---
    broker_kind: str = field(default_factory=lambda: os.environ.get("BROKER_KIND", "paper"))
    broker_api_token: str = field(default_factory=lambda: os.environ.get("BROKER_API_TOKEN", ""))
    broker_account_id: str = field(default_factory=lambda: os.environ.get("BROKER_ACCOUNT_ID", ""))
    broker_env: str = field(default_factory=lambda: os.environ.get("BROKER_ENV", "practice"))

    # --- YAML-loaded blocks ---
    system: dict = field(default_factory=dict)
    risk: dict = field(default_factory=dict)
    strategy: dict = field(default_factory=dict)
    filters: dict = field(default_factory=dict)
    correlation: dict = field(default_factory=dict)
    notifications: dict = field(default_factory=dict)
    symbols: dict = field(default_factory=dict)
    symbol_mapping: dict = field(default_factory=dict)
    sessions: dict = field(default_factory=dict)

    def live_ready(self) -> bool:
        """Live orders require ALL of: env=live, flag on, and a matching phrase."""
        if self.environment != "live" or not self.live_trading_enabled:
            return False
        if not self.live_confirmation_expected:
            return False
        return self.live_confirmation_phrase == self.live_confirmation_expected

    def auth_mode(self) -> str:
        return "hmac" if self.hmac_required else "body_secret+url_token"


def load_settings(config_dir: str | os.PathLike | None = None) -> Settings:
    s = Settings()
    base = Path(config_dir) if config_dir else Path(__file__).resolve().parents[3] / "config"
    if yaml is None:
        return s
    cfg = base / "config.yaml"
    if not cfg.exists():
        cfg = base / "config.example.yaml"
    syms = base / "symbols.yaml"
    if not syms.exists():
        syms = base / "symbols.example.yaml"
    if cfg.exists():
        data = yaml.safe_load(cfg.read_text()) or {}
        s.system = data.get("system", {})
        s.risk = data.get("risk", {})
        s.strategy = data.get("strategy", {})
        s.filters = data.get("filters", {})
        s.correlation = data.get("correlation", {})
        s.notifications = data.get("notifications", {})
    if syms.exists():
        data = yaml.safe_load(syms.read_text()) or {}
        s.symbols = data.get("symbols", {})
        s.symbol_mapping = data.get("symbol_mapping", {})
        s.sessions = data.get("sessions", {})
    return s
