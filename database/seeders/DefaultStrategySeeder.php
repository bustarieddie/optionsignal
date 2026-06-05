<?php

namespace Database\Seeders;

use App\Models\Strategy;
use Illuminate\Database\Seeder;

class DefaultStrategySeeder extends Seeder
{
    public function run(): void
    {
        $strategy = Strategy::firstOrCreate(
            ['user_id' => null, 'name' => 'EMA 9/21 RSI Option Scalping Strategy'],
            [
                'description' => 'Default decision-support strategy. EMA 9/21 crossover with RSI/RSI-MA momentum, VWAP alignment, volume confirmation and higher-timeframe trend. Decision support only — not financial advice.',
                'timeframes' => ['3m', '5m', '15m', '1h'],
                'active' => true,
            ]
        );

        if ($strategy->rules()->exists()) {
            return;
        }

        $rules = [
            // BUY CALL
            ['buy_call', 'ema_crossover', 'EMA 9 crosses above EMA 21'],
            ['buy_call', 'rsi', 'RSI crosses above RSI moving average'],
            ['buy_call', 'vwap', 'Price is above VWAP'],
            ['buy_call', 'htf', 'Higher timeframe trend bullish'],
            ['buy_call', 'volume', 'Volume above average'],
            ['buy_call', 'sr', 'No major resistance nearby'],
            // BUY PUT
            ['buy_put', 'ema_crossover', 'EMA 9 crosses below EMA 21'],
            ['buy_put', 'rsi', 'RSI crosses below RSI moving average'],
            ['buy_put', 'vwap', 'Price is below VWAP'],
            ['buy_put', 'htf', 'Higher timeframe trend bearish'],
            ['buy_put', 'volume', 'Volume above average'],
            ['buy_put', 'sr', 'No major support nearby'],
            // EXIT (descriptive; no scoring component)
            ['exit', null, 'RSI crosses back against its moving average'],
            ['exit', null, 'EMA 9 loses momentum'],
            ['exit', null, 'Price breaks short-term trendline'],
            ['exit', null, 'Price rejects key level'],
            ['exit', null, 'Target reached'],
            ['exit', null, 'Stop loss triggered'],
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
