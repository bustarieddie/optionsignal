<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Real-time quote feed
    |--------------------------------------------------------------------------
    | Polls the active watchlist tickers and broadcasts live prices to the
    | dashboard over Reverb. Decision-support only — quotes may be delayed and
    | are not guaranteed accurate.
    |
    | provider: auto | finnhub | yahoo | null
    |   auto  -> finnhub when FINNHUB_API_KEY is set, otherwise yahoo (keyless)
    */

    'enabled' => env('QUOTE_FEED_ENABLED', true),

    'provider' => env('QUOTE_PROVIDER', 'auto'),

    // Seconds between polls when running `php artisan quotes:poll --watch`.
    'poll_seconds' => (int) env('QUOTE_POLL_SECONDS', 15),

    // How long the latest snapshot is cached (seconds).
    'cache_ttl' => 120,
];
