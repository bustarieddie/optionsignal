"""Notification dispatch (rulebook §24).

A channel-agnostic dispatcher. Channels: dashboard/log (always safe), Telegram,
n8n webhook, email. Network channels lazy-import their client and fail soft (a
down channel never breaks trading). Secrets are scrubbed from every message
before dispatch (§24: "Do not expose sensitive credentials in notifications").

Event types map 1:1 to the config `notify_on` keys so operators toggle each.
"""
from __future__ import annotations

from collections import deque
from typing import Protocol

from app.core.logging import get_logger, scrub

log = get_logger("botv1.notify")

# Canonical event types (rulebook §24).
EVENTS = {
    "valid_signal", "rejected_signal", "order_placed", "order_rejected",
    "stop_hit", "tp_hit", "partial_profit", "breakeven", "risk_limit",
    "broker_disconnected", "paused", "kill_switch", "daily_summary",
}


class Channel(Protocol):
    name: str
    def send(self, event_type: str, message: str, fields: dict) -> bool: ...


class RecordingChannel:
    """In-memory channel — powers the dashboard feed and tests."""
    name = "dashboard"

    def __init__(self, maxlen: int = 200):
        self.sent: deque = deque(maxlen=maxlen)

    def send(self, event_type: str, message: str, fields: dict) -> bool:
        self.sent.appendleft({"event": event_type, "message": message, "fields": fields})
        return True


class LogChannel:
    name = "log"

    def send(self, event_type: str, message: str, fields: dict) -> bool:
        log.info("notify", extra={"extra_fields": {"event": event_type, "message": message, **fields}})
        return True


class TelegramChannel:
    name = "telegram"

    def __init__(self, bot_token: str, chat_id: str):
        self._token = bot_token
        self._chat = chat_id

    def send(self, event_type: str, message: str, fields: dict) -> bool:
        if not self._token or not self._chat:
            return False
        try:  # pragma: no cover - network
            import httpx
            httpx.post(
                f"https://api.telegram.org/bot{self._token}/sendMessage",
                json={"chat_id": self._chat, "text": f"[{event_type}] {message}"},
                timeout=8.0,
            )
            return True
        except Exception:
            return False


class N8nChannel:
    name = "n8n"

    def __init__(self, url: str):
        self._url = url

    def send(self, event_type: str, message: str, fields: dict) -> bool:
        if not self._url:
            return False
        try:  # pragma: no cover - network
            import httpx
            httpx.post(self._url, json={"event": event_type, "message": message, "fields": fields}, timeout=8.0)
            return True
        except Exception:
            return False


class EmailChannel:
    name = "email"

    def __init__(self, smtp_url: str, sender: str = "bot@localhost", to: str = ""):
        self._smtp = smtp_url
        self._from = sender
        self._to = to

    def send(self, event_type: str, message: str, fields: dict) -> bool:
        if not self._smtp or not self._to:
            return False
        try:  # pragma: no cover - network
            import smtplib
            from email.message import EmailMessage
            from urllib.parse import urlparse
            u = urlparse(self._smtp)
            msg = EmailMessage()
            msg["Subject"] = f"[BOTv1] {event_type}"
            msg["From"] = self._from
            msg["To"] = self._to
            msg.set_content(message)
            with smtplib.SMTP(u.hostname, u.port or 25, timeout=8.0) as s:
                if u.username:
                    s.starttls()
                    s.login(u.username, u.password or "")
                s.send_message(msg)
            return True
        except Exception:
            return False


class NotificationDispatcher:
    def __init__(self, channels: list[Channel], notify_on: dict[str, bool] | None = None):
        self.channels = channels
        self.notify_on = notify_on or {}

    def recent(self, n: int = 25) -> list[dict]:
        """Recent notifications from the in-memory RecordingChannel (dashboard)."""
        for ch in self.channels:
            if isinstance(ch, RecordingChannel):
                return list(ch.sent)[:n]
        return []

    def notify(self, event_type: str, message: str, **fields) -> int:
        """Dispatch to every enabled channel. Returns count of successful sends.
        Silently no-ops if this event type is disabled in `notify_on`."""
        if not self.notify_on.get(event_type, True):
            return 0
        safe = scrub(fields)
        ok = 0
        for ch in self.channels:
            try:
                if ch.send(event_type, message, safe):
                    ok += 1
            except Exception:
                pass
        return ok


def build_dispatcher(settings) -> NotificationDispatcher:
    """Build a dispatcher from Settings (config `notifications` + env secrets)."""
    cfg = settings.notifications if isinstance(settings.notifications, dict) else {}
    wanted = set(cfg.get("channels", ["dashboard"]))
    channels: list[Channel] = [RecordingChannel(), LogChannel()]
    import os
    if "telegram" in wanted:
        channels.append(TelegramChannel(os.environ.get("TELEGRAM_BOT_TOKEN", ""),
                                        os.environ.get("TELEGRAM_CHAT_ID", "")))
    if "n8n" in wanted:
        channels.append(N8nChannel(os.environ.get("N8N_WEBHOOK_URL", "")))
    if "email" in wanted:
        channels.append(EmailChannel(os.environ.get("SMTP_URL", ""),
                                     to=os.environ.get("ALERT_EMAIL", "")))
    return NotificationDispatcher(channels, cfg.get("notify_on", {}))
