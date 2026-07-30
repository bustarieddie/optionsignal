"""Webhook + admin authentication (rulebook §18, §30; see docs/05).

Two webhook auth modes, honestly reported at /health:
  • body-secret + unguessable URL token (+ optional IP allow-list)  — TradingView-direct
  • HMAC-SHA256 over the raw body via X-Signature                   — when a signer proxy exists

All comparisons are constant-time. Admin endpoints use a bearer token with a
simple failure-lockout counter.
"""
from __future__ import annotations

import hashlib
import hmac
import time
from dataclasses import dataclass, field


def constant_time_eq(a: str, b: str) -> bool:
    return hmac.compare_digest(a.encode(), b.encode())


def verify_body_secret(payload_secret: str | None, expected: str) -> bool:
    if not expected or not payload_secret:
        return False
    return constant_time_eq(payload_secret, expected)


def compute_hmac(raw_body: bytes, secret: str) -> str:
    return hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()


def verify_hmac(raw_body: bytes, header_value: str | None, secret: str) -> bool:
    if not header_value:
        return False
    provided = header_value.split("=", 1)[-1].strip()  # "sha256=<hex>" or "<hex>"
    expected = compute_hmac(raw_body, secret)
    return hmac.compare_digest(provided, expected)


def verify_url_token(token_in_path: str, expected: str) -> bool:
    if not expected:
        return False
    return constant_time_eq(token_in_path, expected)


@dataclass
class AuthConfig:
    webhook_secret: str
    url_token: str
    hmac_required: bool = False
    ip_allowlist: tuple[str, ...] = ()

    def mode(self) -> str:
        return "hmac" if self.hmac_required else "body_secret+url_token"


def authenticate_webhook(
    *,
    cfg: AuthConfig,
    url_token: str,
    raw_body: bytes,
    payload_secret: str | None,
    signature_header: str | None,
    client_ip: str | None,
) -> tuple[bool, str | None]:
    """Return (ok, failure_reason). Enforces the active mode's requirements."""
    if not verify_url_token(url_token, cfg.url_token):
        return False, "bad_url_token"
    if cfg.ip_allowlist and (client_ip not in cfg.ip_allowlist):
        return False, "ip_not_allowed"
    if cfg.hmac_required:
        if not verify_hmac(raw_body, signature_header, cfg.webhook_secret):
            return False, "bad_hmac"
        return True, None
    # body-secret mode
    if not verify_body_secret(payload_secret, cfg.webhook_secret):
        return False, "bad_secret"
    return True, None


@dataclass
class AdminAuth:
    """Bearer-token admin auth with lockout after repeated failures (§30)."""
    token: str
    max_failures: int = 5
    lockout_seconds: int = 300
    _failures: int = field(default=0, init=False)
    _locked_until: float = field(default=0.0, init=False)

    def check(self, provided: str | None) -> tuple[bool, str | None]:
        now = time.monotonic()
        if now < self._locked_until:
            return False, "locked_out"
        if provided and constant_time_eq(provided, self.token):
            self._failures = 0
            return True, None
        self._failures += 1
        if self._failures >= self.max_failures:
            self._locked_until = now + self.lockout_seconds
            self._failures = 0
        return False, "bad_token"
