"""Signal deduplication + per-candle lock (rulebook §18.2, R-9).

Backed by Redis in production (atomic SETNX + TTL). For local dev and tests an
in-memory implementation with the same interface is provided, so the whole core
runs without Redis. Repeated webhook deliveries must never create duplicate orders.
"""
from __future__ import annotations

import time
from typing import Protocol


class DedupStore(Protocol):
    def claim(self, key: str, ttl_seconds: int) -> bool:
        """Atomically claim `key`. Returns True if newly claimed, False if it
        already existed (i.e. a duplicate)."""
        ...


class InMemoryDedupStore:
    """Process-local store. Fine for a single dev instance and tests."""

    def __init__(self) -> None:
        self._keys: dict[str, float] = {}

    def _purge(self, now: float) -> None:
        expired = [k for k, exp in self._keys.items() if exp <= now]
        for k in expired:
            del self._keys[k]

    def claim(self, key: str, ttl_seconds: int) -> bool:
        now = time.monotonic()
        self._purge(now)
        if key in self._keys:
            return False
        self._keys[key] = now + ttl_seconds
        return True


class RedisDedupStore:
    """Production store. `redis_client` is an object exposing .set(name, value,
    nx=True, ex=ttl) → truthy on success (redis-py compatible)."""

    def __init__(self, redis_client) -> None:
        self._r = redis_client

    def claim(self, key: str, ttl_seconds: int) -> bool:
        return bool(self._r.set(name=key, value="1", nx=True, ex=ttl_seconds))


def signal_key(signal_id: str) -> str:
    return f"botv1:sig:{signal_id}"


def candle_key(symbol: str, timeframe: str, bar_time_iso: str) -> str:
    return f"botv1:candle:{symbol}:{timeframe}:{bar_time_iso}"
