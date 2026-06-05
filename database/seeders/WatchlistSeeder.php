<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Database\Seeder;

class WatchlistSeeder extends Seeder
{
    /** Default seed universe of liquid, optionable US tickers. */
    public const TICKERS = [
        ['ticker' => 'NVDA', 'company' => 'NVIDIA Corporation', 'sector' => 'Technology'],
        ['ticker' => 'TSLA', 'company' => 'Tesla, Inc.', 'sector' => 'Consumer Cyclical'],
        ['ticker' => 'META', 'company' => 'Meta Platforms, Inc.', 'sector' => 'Communication Services'],
        ['ticker' => 'AAPL', 'company' => 'Apple Inc.', 'sector' => 'Technology'],
        ['ticker' => 'SPY', 'company' => 'SPDR S&P 500 ETF Trust', 'sector' => 'Index ETF'],
        ['ticker' => 'QQQ', 'company' => 'Invesco QQQ Trust', 'sector' => 'Index ETF'],
        ['ticker' => 'AMD', 'company' => 'Advanced Micro Devices, Inc.', 'sector' => 'Technology'],
        ['ticker' => 'MSFT', 'company' => 'Microsoft Corporation', 'sector' => 'Technology'],
        ['ticker' => 'GOOG', 'company' => 'Alphabet Inc.', 'sector' => 'Communication Services'],
    ];

    public function run(): void
    {
        // Seed the watchlist for every user who has an empty one.
        User::all()->each(function (User $user) {
            if ($user->watchlists()->exists()) {
                return;
            }

            foreach (self::TICKERS as $row) {
                Watchlist::create([
                    'user_id' => $user->id,
                    'ticker' => $row['ticker'],
                    'company' => $row['company'],
                    'sector' => $row['sector'],
                    'optionable' => true,
                    'preferred_timeframe' => '5m',
                    'active' => true,
                ]);
            }
        });
    }
}
