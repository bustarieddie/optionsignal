<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default risk-management settings
    |--------------------------------------------------------------------------
    | Seeded into a user's risk_settings row on registration. All percentages
    | are whole numbers (e.g. 2 = 2%). These are conservative decision-support
    | defaults — not financial advice.
    */

    'defaults' => [
        'max_daily_loss'              => 3.0,  // % of account
        'max_trades_per_day'          => 5,
        'risk_per_trade_pct'          => 2.0,  // % of account (1–2% recommended)
        'max_position_size'           => 10.0, // % of account per position
        'stop_loss_pct'               => 25.0, // % of option premium (20–30%)
        'take_profit_pct'             => 40.0, // % of option premium (30–50%)
        'cooldown_minutes_after_loss' => 30,
        'no_trade_window_start'       => null, // e.g. "09:30"
        'no_trade_window_end'         => null, // e.g. "09:45"
    ],
];
