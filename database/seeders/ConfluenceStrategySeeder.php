<?php

namespace Database\Seeders;

use App\Models\Strategy;
use Illuminate\Database\Seeder;

class ConfluenceStrategySeeder extends Seeder
{
    public function run(): void
    {
        $strategy = Strategy::firstOrCreate(
            ['user_id' => null, 'name' => 'Confluence ORB Squeeze'],
            [
                'description' => 'High-conviction multi-confluence strategy. Fires only when SuperTrend, EMA 9/21, RSI/RSI-MA, VWAP, a released TTM squeeze with momentum, volume, the higher-timeframe trend, and an opening-range breakout ALL align. Inspired by a multi-indicator NVDA setup. Decision support only — not financial advice.',
                'timeframes' => ['3m', '5m', '15m'],
                'active' => true,
            ]
        );

        if ($strategy->rules()->exists()) {
            return;
        }

        $rules = [
            // BUY CALL — all must align
            ['buy_call', 'ema_crossover', 'SuperTrend bullish flip & EMA 9 > EMA 21'],
            ['buy_call', 'rsi', 'RSI above its moving average'],
            ['buy_call', 'vwap', 'Price above VWAP'],
            ['buy_call', 'volume', 'Volume above average (squeeze released)'],
            ['buy_call', 'htf', 'Higher-timeframe trend bullish'],
            ['buy_call', 'sr', 'Clean breakout above the opening range high'],
            // BUY PUT — inverse, all must align
            ['buy_put', 'ema_crossover', 'SuperTrend bearish flip & EMA 9 < EMA 21'],
            ['buy_put', 'rsi', 'RSI below its moving average'],
            ['buy_put', 'vwap', 'Price below VWAP'],
            ['buy_put', 'volume', 'Volume above average (squeeze released)'],
            ['buy_put', 'htf', 'Higher-timeframe trend bearish'],
            ['buy_put', 'sr', 'Clean breakdown below the opening range low'],
            // EXIT
            ['exit', null, 'SuperTrend flips against the position'],
            ['exit', null, 'RSI crosses back through its moving average'],
            ['exit', null, 'Squeeze momentum fades / target or stop reached'],
        ];

        foreach ($rules as $i => [$type, $component, $condition]) {
            $strategy->rules()->create([
                'rule_type' => $type,
                'component' => $component,
                'condition_key' => $condition,
                'sort' => $i,
            ]);
        }
    }
}
