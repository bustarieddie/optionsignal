"""Database engine/session factory. SQLite for dev/tests, Postgres in prod."""
from __future__ import annotations

from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker

from app.core.config import load_settings
from app.db.models import Base

_settings = load_settings()
_engine = create_engine(_settings.database_url, future=True)
SessionLocal = sessionmaker(bind=_engine, class_=Session, expire_on_commit=False, future=True)


def init_db() -> None:
    """Create tables for dev. Production should use Alembic migrations generated
    from these models."""
    Base.metadata.create_all(_engine)


def get_session() -> Session:
    return SessionLocal()
