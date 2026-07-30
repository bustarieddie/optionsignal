# Database migrations (Alembic)

The SQLAlchemy models in `app/db/models.py` are the source of truth for all §22
tables. Alembic is wired to their metadata (`migrations/env.py`), so the initial
migration is generated — not hand-written — to guarantee it matches the models.

## First-time setup (per environment)

```bash
cd bot-trading-v1/backend
pip install -r requirements.txt          # includes alembic
export DATABASE_URL=postgresql+psycopg://botuser:botpass@localhost:5432/botdb

# 1) generate the initial migration from the models
alembic revision --autogenerate -m "init schema"

# 2) apply it
alembic upgrade head
```

The generated file lands in `migrations/versions/` and IS committed to version
control (that's how every environment gets the same schema).

## Ongoing changes

1. Edit `app/db/models.py`.
2. `alembic revision --autogenerate -m "describe change"`.
3. Review the generated script (autogenerate is a draft, not gospel — check
   type/`server_default` changes).
4. `alembic upgrade head` (or `alembic downgrade -1` to roll back).

## Notes
- `DATABASE_URL` is read from the environment; no secret lives in `alembic.ini`.
- Dev/tests use `init_db()` (`create_all`) for zero-setup SQLite; production must
  use these migrations so schema changes are reviewable and reversible.
- `compare_type=True` is enabled so column-type changes are detected.
