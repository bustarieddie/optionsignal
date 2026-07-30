"""Structured, secret-scrubbing logger (rulebook §23, §30).

Never write secrets to logs. Known-sensitive keys are redacted before emit. Logs
are JSON lines so they ship cleanly to any aggregator.
"""
from __future__ import annotations

import json
import logging
import sys
from datetime import datetime, timezone

SENSITIVE_KEYS = {
    "secret", "webhook_secret", "url_token", "admin_token", "password",
    "api_key", "api_secret", "token", "authorization", "credentials",
    "live_confirmation_phrase",
}


def scrub(data):
    if isinstance(data, dict):
        return {k: ("***REDACTED***" if k.lower() in SENSITIVE_KEYS else scrub(v)) for k, v in data.items()}
    if isinstance(data, (list, tuple)):
        return [scrub(v) for v in data]
    return data


class JsonFormatter(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        payload = {
            "ts": datetime.now(timezone.utc).isoformat(),
            "level": record.levelname,
            "logger": record.name,
            "msg": record.getMessage(),
        }
        if hasattr(record, "extra_fields"):
            payload.update(scrub(record.extra_fields))  # type: ignore[attr-defined]
        return json.dumps(payload, default=str)


def get_logger(name: str = "botv1") -> logging.Logger:
    logger = logging.getLogger(name)
    if not logger.handlers:
        h = logging.StreamHandler(sys.stdout)
        h.setFormatter(JsonFormatter())
        logger.addHandler(h)
        logger.setLevel(logging.INFO)
    return logger


def log_event(logger: logging.Logger, msg: str, **fields) -> None:
    rec = logger.makeRecord(logger.name, logging.INFO, __file__, 0, msg, None, None)
    rec.extra_fields = fields
    logger.handle(rec)
