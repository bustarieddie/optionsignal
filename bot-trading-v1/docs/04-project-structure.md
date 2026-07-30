# Part D — Project Structure

The BOT TRADING v1.0 subsystem is fully contained under `bot-trading-v1/`.

```
bot-trading-v1/
├── README.md                       # overview, safety warning, deliverables map
├── docker-compose.yml              # api + postgres + redis
├── .env.example                    # every credential/config knob (no secrets)
│
├── docs/
│   ├── 01-architecture.md          # Part A
│   ├── 02-rulebook.md              # Part B — exact rule table
│   ├── 03-state-machine.md         # Part C
│   ├── 04-project-structure.md     # Part D (this file)
│   ├── 05-webhook-and-security.md  # payload schema + honest security model
│   └── 06-deployment.md            # Phase 6 — deploy/ops/recovery
│
├── config/
│   ├── config.example.yaml         # system + risk + strategy defaults
│   └── symbols.example.yaml        # per-symbol presets + broker symbol mapping
│
├── pine/
│   └── BotTradingV1.pine           # Part E — MTF strategy, non-repaint, JSON webhook
│
├── n8n/
│   └── README.md                   # Phase 4 — importable workflow notes
│
└── backend/                        # Part F — FastAPI foundation
    ├── requirements.txt
    ├── Dockerfile
    ├── pytest.ini
    ├── app/
    │   ├── main.py                 # FastAPI app, lifespan, state machine wiring
    │   ├── core/
    │   │   ├── config.py           # env + YAML loader (pydantic-settings)
    │   │   ├── reject_codes.py     # single source of truth for REJECT_* codes
    │   │   ├── states.py           # SystemState enum + transition log helper
    │   │   ├── security.py         # secret + HMAC verification, admin auth
    │   │   ├── idempotency.py      # Redis/in-memory dedup + per-candle lock
    │   │   └── logging.py          # structured, secret-scrubbing logger
    │   ├── schemas/
    │   │   ├── webhook.py          # TradingView payload (pydantic v2) + validators
    │   │   └── domain.py           # Signal, OrderIntent, RiskDecision, Position
    │   ├── services/
    │   │   ├── validation.py       # Signal Validation Engine
    │   │   ├── position_sizing.py  # deterministic sizing (contract/tick/lot aware)
    │   │   ├── risk_engine.py      # all portfolio/risk gates + correlation
    │   │   ├── sessions.py         # timezone/DST-aware session filter
    │   │   ├── news.py             # news-filter interface (honest 'unavailable')
    │   │   └── executor.py         # orchestrates validate→risk→broker + emergency
    │   ├── brokers/
    │   │   ├── base.py             # BrokerAdapter interface (all methods)
    │   │   ├── paper.py            # paper-trading engine (spread/slippage/SL/TP)
    │   │   ├── mock.py             # deterministic mock for tests
    │   │   └── live_template.py    # template live adapter — NotImplemented by design
    │   ├── api/
    │   │   └── routes/
    │   │       ├── webhook.py      # POST /webhook/tradingview
    │   │       ├── admin.py        # kill-switch/pause/resume/close/risk-status
    │   │       └── read.py         # /health /signals /trades /positions
    │   └── db/
    │       ├── models.py           # SQLAlchemy models (all §22 tables)
    │       └── session.py          # engine/session factory
    └── tests/
        ├── conftest.py
        ├── test_position_sizing.py
        ├── test_risk_engine.py
        ├── test_idempotency.py
        ├── test_signal_expiry.py
        ├── test_sessions.py
        ├── test_webhook_security.py
        ├── test_kill_switch.py
        └── test_paper_broker.py
```

### Design rules honored
- **Modular** — each engine is a file with one responsibility (Part A §A.2).
- **Strict typing** — pydantic v2 schemas + typed service signatures.
- **No oversized files** — engines split by concern; reject codes centralized.
- **No secrets in source** — everything via `core/config.py` from env/YAML.
- **Deterministic business logic** — sizing, risk and validation are pure functions of inputs.

### Next
Part E — the Pine Script strategy (`pine/BotTradingV1.pine`), then Part F (backend code).
