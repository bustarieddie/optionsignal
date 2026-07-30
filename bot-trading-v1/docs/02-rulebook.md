# Part B — Exact Trading Rulebook

BOT TRADING v1.0 — every rule is a **measurable** condition. No vague terms
("strong trend", "good candle") appear without a numeric definition.

**Notation**
- `EMA(n, TF)` = exponential moving average of length `n` on timeframe `TF`, on the **confirmed** bar.
- `slope+(x)` = `x[0] > x[k]` (rising over the last `k` confirmed bars); `slope-(x)` = `x[0] < x[k]`. Default `k = slope_lookback = 3`.
- `SwingH/SwingL` = pivot high/low confirmed with `pivot_left`/`pivot_right` bars (default 3/3). A pivot is only used **after** `pivot_right` bars have closed (see repaint note in `01-architecture.md` / Pine header).
- `ATR(TF)` = ATR(14) on that timeframe.
- All comparisons use **confirmed** bar values only.

Direction is evaluated per symbol. BUY and SELL are evaluated **independently**. A trade opens
only if **every required layer** below is valid.

---

## B.1 4H — Market Direction

| Rule ID | TF | BUY (Bullish) condition | SELL (Bearish) condition | Invalidation → NEUTRAL | Configurable params |
|---------|----|-----------------------|--------------------------|------------------------|---------------------|
| **D-1 EMA stack** | 4H | `EMA(50) > EMA(200)` | `EMA(50) < EMA(200)` | EMAs within `ema_min_gap_atr × ATR(4H)` of each other | `ema_fast=50`, `ema_slow=200`, `ema_min_gap_atr=0.10` |
| **D-2 Price vs EMA50** | 4H | `close > EMA(50)` | `close < EMA(50)` | close on wrong side of EMA50 | — |
| **D-3 EMA50 slope** | 4H | `slope+(EMA50)` | `slope-(EMA50)` | slope flat/opposite | `slope_lookback=3` |
| **D-4 Structure** | 4H | `SwingH[0] > SwingH[1]` AND `SwingL[0] > SwingL[1]` (HH+HL) | `SwingH[0] < SwingH[1]` AND `SwingL[0] < SwingL[1]` (LH+LL) | structure not confirmed / mixed | `pivot_left=3`, `pivot_right=3` |
| **D-5 ADX (optional)** | 4H | `ADX(14) ≥ min_adx` | `ADX(14) ≥ min_adx` | ADX below min | `use_adx_filter=true`, `adx_period=14`, `min_adx=20` |

**Direction = BULLISH** iff D-1..D-4 bullish (and D-5 if enabled). **BEARISH** iff D-1..D-4 bearish (and D-5). Otherwise **NEUTRAL ⇒ NO TRADE** (`REJECT_4H_NEUTRAL`).

---

## B.2 1H — Trend Confirmation

| Rule ID | TF | BUY condition | SELL condition | Invalidation | Configurable params |
|---------|----|--------------|----------------|--------------|---------------------|
| **T-0 Agreement** | 1H | 4H direction == BULLISH | 4H direction == BEARISH | 4H direction changed/neutral | — |
| **T-1 EMA stack** | 1H | `EMA(20) > EMA(50)` | `EMA(20) < EMA(50)` | stack flips | `ema_fast=20`, `ema_slow=50` |
| **T-2 Price vs EMA** | 1H | `close > EMA(20)` OR `close > EMA(50)` | `close < EMA(20)` OR `close < EMA(50)` | close fully on wrong side | — |
| **T-3 EMA20 slope** | 1H | `slope+(EMA20)` | `slope-(EMA20)` | slope opposite | `slope_lookback=3` |
| **T-4 EMA50 slope** | 1H | `slope+(EMA50)` | `slope-(EMA50)` | slope opposite | `slope_lookback=3` |
| **T-5 Structure** | 1H | latest confirmed 1H structure bullish | bearish | structure flips | `pivot_left=3`, `pivot_right=3` |
| **T-6 Extension** | 1H | `abs(close − EMA20) ≤ ext_atr_mult × ATR(1H)` | same | price too far from EMA20 | `ext_atr_mult=1.5` |

**1H trend valid** iff T-0..T-6 all true for the side. Else `REJECT_1H_TREND_MISMATCH`.

---

## B.3 15M — Pullback Setup

| Rule ID | TF | BUY condition | SELL condition | Invalidation | Configurable params |
|---------|----|--------------|----------------|--------------|---------------------|
| **P-0 Context** | 15M | 4H BULLISH AND 1H bullish | 4H BEARISH AND 1H bearish | context lost | — |
| **P-1 Retrace-to-zone** | 15M | price touches ≥1 of: `EMA(20)`, `EMA(50)`, prior breakout level, prior support, (optional) Fib `38.2–61.8%` | mirror: EMA20/50, prior breakdown, prior resistance, optional Fib | none in zone | `use_fib=false`, `fib_low=0.382`, `fib_high=0.618` |
| **P-2 RSI band** | 15M | `rsi_buy_min ≤ RSI(14) ≤ rsi_buy_max` (default **35–50**) | `rsi_sell_min ≤ RSI(14) ≤ rsi_sell_max` (default **50–65**) | RSI outside band | `rsi_len=14`, `rsi_buy_min=35`, `rsi_buy_max=50`, `rsi_sell_min=50`, `rsi_sell_max=65` |
| **P-3 No reversal** | 15M | no confirmed bearish reversal (opposite BOS/CHoCH) | no confirmed bullish reversal | opposite reversal confirmed | — |
| **P-4 Depth cap** | 15M | pullback depth `≤ pullback_max_atr × ATR(15M)` from swing | same | depth exceeds cap | `pullback_max_atr=2.0` |
| **P-5 Expiry** | 15M | setup unfilled ≤ `setup_expiry_bars` (default **12**) | same | expired | `setup_expiry_bars=12` |

**Pullback invalidation** (any ⇒ drop setup, log reason): 1H no longer agrees with 4H;
price closes beyond structural invalidation point; depth > cap (P-4); expiry (P-5); prohibited
news/session begins; 4H bias becomes NEUTRAL. Else `REJECT_NO_PULLBACK`.

---

## B.4 5M — Entry Trigger

**Required context:** 4H direction + 1H trend + 15M pullback all valid for the side.

| Rule ID | TF | BUY (primary) | SELL (primary) | Invalidation | Configurable params |
|---------|----|--------------|----------------|--------------|---------------------|
| **E-1 CHoCH/BOS** | 5M | bullish CHoCH or BOS confirmed | bearish CHoCH or BOS confirmed | no structural break | `pivot_left=3`, `pivot_right=3` |
| **E-2 Break level** | 5M | `close > most-recent confirmed 5M lower-high` | `close < most-recent confirmed 5M higher-low` | close doesn't clear level | — |
| **E-3 Close-through** | 5M | breakout candle **closes** above structure (not just a wick) | closes below structure | wick-only | — |
| **E-4 Not oversized** | 5M | `candle_range ≤ trigger_max_atr × ATR(5M)` (default **2.0**) | same | candle too large | `trigger_max_atr=2.0` |
| **E-5 Entry-vs-invalidation** | 5M | distance entry→invalidation within stop bounds (see S-rules) | same | too far | see §B.5 |

**Optional confirmations** (each individually toggleable; `min_confirmations` of them must pass, default 0):
bullish/bearish engulfing · rejection candle from zone · `volume > SMA(volume, vol_len)` ·
RSI cross of 50 · fast-EMA cross of slow-EMA · breakout→retest.
Params: `min_confirmations=0`, `vol_len=20`, plus a bool per confirmation.

**Entry modes** (`entry_mode`, default **retest**):
- **breakout** — enter at close of the confirmed trigger candle.
- **retest** *(default)* — wait ≤ `retest_max_bars` (default 6) for price to retest the broken structure, then enter.
- **stop** — place buy-stop above trigger high / sell-stop below trigger low (`stop_offset_atr=0.05`).

No valid trigger ⇒ `REJECT_NO_5M_TRIGGER`.

---

## B.5 Stop-Loss (`stop_method`, default **structural**)

| Rule ID | Method | BUY stop | SELL stop | Configurable |
|---------|--------|----------|-----------|--------------|
| **S-1** | structural *(default)* | below latest valid 5M swing low − buffer | above latest valid 5M swing high + buffer | `sl_buffer_atr=0.20` |
| **S-2** | atr | entry − `atr_stop_mult × ATR(5M)` | entry + `atr_stop_mult × ATR(5M)` | `atr_stop_mult=1.5` |
| **S-3** | trigger_candle | below trigger low − buffer | above trigger high + buffer | `sl_buffer_atr=0.20` |

**Trade rejected when:** stop distance `< min_stop_distance` (`REJECT_STOP_TOO_TIGHT`) · stop distance
`> max_stop_atr × ATR(5M)` (`REJECT_STOP_TOO_WIDE`) · broker stop-level not satisfied · sized lots
`< broker min lot` (`REJECT_POSITION_TOO_SMALL`) · spread makes risk unacceptable (`REJECT_SPREAD_HIGH`).
Params: `min_stop_distance` (per-symbol), `max_stop_atr=3.0`.

---

## B.6 Take-Profit & Management (`exit_mode`, default **fixed_rr**)

`R = |entry − stop|`.

| Rule ID | Mode | Behaviour | Configurable |
|---------|------|-----------|--------------|
| **X-1** | fixed_rr *(default)* | TP = entry ± `reward_risk × R` (default **2.0R**) | `reward_risk=2.0` |
| **X-2** | partial | close `p1_pct` at `p1_r` R (default 50% @ 1R); move stop to breakeven after `be_r` (default 1R); close remainder at `reward_risk` | `p1_pct=50`, `p1_r=1.0`, `be_r=1.0` |
| **X-3** | atr_trail | after price reaches `trail_activate_r` (default 1R), trail by `trail_atr_mult × ATR(5M)` (default 1.5) | `trail_activate_r=1.0`, `trail_atr_mult=1.5` |
| **X-4** | structure_exit | exit remainder on opposite 5M CHoCH, 15M trend invalidation, or 1H reversal | — |
| **X-5** | time_exit | close/review trade open > `max_trade_bars` 5M candles without meaningful progress (default **48**) | `max_trade_bars=48` |

All exit actions are logged with a reason.

---

## B.7 Position Sizing (deterministic)

```
RiskAmount   = AccountEquity × (risk_per_trade_percent / 100)
LossPerUnit  = StopDistance(price) × TickValue / TickSize        # in account currency
              (× FX conversion when quote currency ≠ account currency)
RawSize      = RiskAmount / (LossPerUnit + est_commission_per_unit + slippage_allowance)
Size         = floor(RawSize / lot_step) × lot_step
```

Rejections: `Size < min_lot` ⇒ `REJECT_POSITION_TOO_SMALL`; `Size` capped at `max_lot`; any input
missing/zero (tick value, contract size) ⇒ `REJECT_SIZING_UNRELIABLE`. **Fixed lot is never the default.**
Params: `risk_per_trade_percent` (0.10–1.00, default **0.5**, hard max `risk_hard_max_percent=1.0`),
plus per-symbol `contract_size`, `tick_size`, `tick_value`, `min_lot`, `max_lot`, `lot_step`,
`est_commission_per_unit`, `slippage_allowance`.

---

## B.8 Portfolio & Risk rules (`R-RISK`)

| Rule ID | Rule | Default | Reject code |
|---------|------|---------|-------------|
| **R-1** | Risk per trade | 0.5% | (sizing) |
| **R-2** | Max total open risk | 1.5% | `REJECT_MAX_OPEN_RISK` |
| **R-3** | Max trades per day | 3 | `REJECT_MAX_TRADES` |
| **R-4** | Max losing trades per day | 2 | `REJECT_MAX_DAILY_LOSSES` |
| **R-5** | Max consecutive losses | 3 | `REJECT_MAX_CONSEC_LOSSES` |
| **R-6** | Daily loss limit | 2% | `REJECT_DAILY_LOSS_LIMIT` |
| **R-7** | Weekly loss limit | 5% | `REJECT_WEEKLY_LOSS_LIMIT` |
| **R-8** | One open trade per symbol | — | `REJECT_SYMBOL_ALREADY_OPEN` |
| **R-9** | One new signal per candle | — | `REJECT_DUPLICATE_CANDLE` |
| **R-10** | Correlated index-group open risk | ≤ 1% | `REJECT_CORRELATED_EXPOSURE` |
| **R-11** | Live trading enabled | false | `REJECT_LIVE_TRADING_DISABLED` |

**Forbidden by design (not configurable on):** martingale, grid, averaging into losers,
automatic risk increase after a loss, revenge-trading logic.

When a limit is reached the bot: (1) blocks new trades, (2) keeps existing protected positions
managed per strategy, (3) records the reason, (4) alerts, (5) requires the configured reset
(daily reset in `timezone`, default **Asia/Kuching**) before resuming ⇒ state `RISK_LOCKED`.

---

## B.9 Correlation group

`NAS100`, `US30`, `SPX500` form one configurable correlation group. New same-direction index
trade is blocked when combined open group risk would exceed `max_index_group_risk_percent`
(default **1.0%**). Gold is separate unless configured otherwise. See R-10.

---

## B.10 Session, News, Spread/Volatility filters

| Rule ID | Filter | Rule | Reject code |
|---------|--------|------|-------------|
| **F-SESS** | Session | trade only inside configured, timezone/DST-aware sessions (Gold: London/NY/overlap; indices: NY cash, optional pre-market/first-hour/last-minutes avoidance) | `REJECT_SESSION` |
| **F-NEWS** | News | block `news_before_min`/`news_after_min` (default 30/30; longer for FOMC/NFP) around high-impact events; if no verified feed connected → status **unavailable** and (default) block when `use_news_filter=true` | `REJECT_NEWS_WINDOW` |
| **F-SPREAD** | Spread | reject if `spread > max_spread` (per symbol) | `REJECT_SPREAD_HIGH` |
| **F-SLIP** | Slippage | reject if modeled slippage > tolerance | `REJECT_SLIPPAGE` |
| **F-BIGCDL** | Trigger candle | reject if `range > trigger_max_atr × ATR(5M)` (default 2×) | `REJECT_TRIGGER_CANDLE_LARGE` |
| **F-VOLLO** | Min volatility | reject if `ATR(5M) < min_atr` | `REJECT_VOLATILITY_LOW` |
| **F-VOLHI** | Max volatility | reject if `ATR(5M) > max_atr` | `REJECT_VOLATILITY_HIGH` |
| **F-GAP** | Gap | reject if market gapped through planned entry | `REJECT_GAP` |
| **F-DEV** | Signal price deviation | reject if `abs(latest_price − entry) > signal_dev_atr × ATR(5M)` (default 0.25×) | `REJECT_SIGNAL_DEVIATION` |

---

## B.11 Complete rejection-code catalogue

```
REJECT_BAD_SIGNATURE        REJECT_MALFORMED           REJECT_DUPLICATE_SIGNAL
REJECT_SIGNAL_EXPIRED       REJECT_UNKNOWN_SYMBOL      REJECT_SIGNAL_DEVIATION
REJECT_4H_NEUTRAL           REJECT_1H_TREND_MISMATCH   REJECT_NO_PULLBACK
REJECT_NO_5M_TRIGGER        REJECT_SESSION             REJECT_NEWS_WINDOW
REJECT_SPREAD_HIGH          REJECT_SLIPPAGE            REJECT_TRIGGER_CANDLE_LARGE
REJECT_VOLATILITY_LOW       REJECT_VOLATILITY_HIGH     REJECT_GAP
REJECT_STOP_TOO_TIGHT       REJECT_STOP_TOO_WIDE       REJECT_POSITION_TOO_SMALL
REJECT_SIZING_UNRELIABLE    REJECT_MAX_TRADES          REJECT_MAX_DAILY_LOSSES
REJECT_MAX_CONSEC_LOSSES    REJECT_DAILY_LOSS_LIMIT    REJECT_WEEKLY_LOSS_LIMIT
REJECT_MAX_OPEN_RISK        REJECT_SYMBOL_ALREADY_OPEN REJECT_DUPLICATE_CANDLE
REJECT_CORRELATED_EXPOSURE  REJECT_LIVE_TRADING_DISABLED
```

The reject codes are defined in one place in code: `backend/app/core/reject_codes.py`.

---

### Files created (Part B)
- `docs/02-rulebook.md`

### Assumptions
- Pivot/structure detection uses symmetric `pivot_left=pivot_right=3` and is only read after
  confirmation, accepting the resulting `pivot_right`-bar delay (documented in the Pine header).
- "Meaningful progress" for X-5 is defined as reaching ≥ `p1_r` R at any point; otherwise the
  trade is a time-exit candidate.

### Known limitations
- Fib retracement (P-1) depends on automated swing selection and is therefore **off by default**.
- News (F-NEWS) requires an external feed; without one the honest status is `unavailable`.

### Next
Part C — the state machine (`03-state-machine.md`).
