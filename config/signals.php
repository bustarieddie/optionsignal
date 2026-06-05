<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Confidence scoring rubric
    |--------------------------------------------------------------------------
    | Additive points awarded by App\Services\Signals\ConfidenceScorer when the
    | corresponding condition is confirmed for an incoming TradingView signal.
    | These are the *weights*; the per-strategy conditions that decide whether a
    | component is "confirmed" live in the strategy_rules table (user-editable).
    */

    'weights' => [
        'ema_crossover' => 20, // EMA 9 / EMA 21 crossover confirmed
        'rsi'           => 20, // RSI crosses its moving average in the signal direction
        'vwap'          => 20, // Price aligned with VWAP (above for calls, below for puts)
        'volume'        => 15, // Volume above its moving average
        'htf'           => 15, // Higher-timeframe trend aligned
        'sr'            => 10, // Clean support/resistance (no nearby barrier)
    ],

    /*
    |--------------------------------------------------------------------------
    | Grade thresholds (inclusive lower bound)
    |--------------------------------------------------------------------------
    | Signals scoring below the lowest band are graded "ignore".
    */

    'grades' => [
        'A+' => 90,
        'A'  => 80,
        'B'  => 70,
        'C'  => 60,
        // anything < 60 => 'ignore'
    ],

    // Minimum grade that is surfaced as an actionable trade idea / notified.
    'min_actionable_score' => 60,

    /*
    |--------------------------------------------------------------------------
    | Option contract suggestion criteria (decision-support only — NO broker)
    |--------------------------------------------------------------------------
    */
    'option_suggestion' => [
        'delta_min' => 0.35,
        'delta_max' => 0.60,
        'expiry_hint' => 'Nearest liquid weekly or monthly expiry',
        'liquidity_note' => 'Prefer tight bid/ask spread with high volume & open interest. Avoid very illiquid contracts.',
        'risk_note' => 'Verify the live option chain manually before any trade. This is decision support, not financial advice.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbol allowlist — webhooks for unknown tickers are rejected.
    | Empty array = allow any uppercase 1-5 char ticker.
    |--------------------------------------------------------------------------
    */
    'allowed_symbols' => ['NVDA', 'TSLA', 'META', 'AAPL', 'SPY', 'QQQ', 'AMD', 'MSFT', 'GOOG'],

    // Accepted timeframes for incoming signals.
    'timeframes' => ['3m', '5m', '15m', '1h'],

    // Accepted signal types.
    'signal_types' => ['buy_call', 'buy_put', 'exit'],

    // When false, incoming "exit" alerts are acknowledged but NOT stored as
    // signals (keeps the feed to entries only). Set true to keep exit signals.
    'store_exit_signals' => env('STORE_EXIT_SIGNALS', false),

    // Dedupe window in seconds — identical ticker/timeframe/signal within this
    // bucket is treated as a duplicate.
    'dedupe_window_seconds' => 60,

    /*
    |--------------------------------------------------------------------------
    | Trade levels (entry / stop / take-profits)
    |--------------------------------------------------------------------------
    | When the webhook supplies `atr`, levels are ATR multiples of the entry.
    | Otherwise they fall back to a percentage of the entry price. Levels are
    | on the UNDERLYING and are decision-support only — not order instructions.
    */
    'levels' => [
        'sl_atr'  => 1.0,
        'tp1_atr' => 1.0,
        'tp2_atr' => 1.5,
        'tp3_atr' => 2.0,
        // % of entry price fallback when ATR is not supplied
        'sl_pct'  => 1.0,
        'tp1_pct' => 1.0,
        'tp2_pct' => 1.5,
        'tp3_pct' => 2.0,
    ],
];
