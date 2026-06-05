<?php

namespace Database\Seeders;

use App\Models\Strategy;
use Illuminate\Database\Seeder;

class VssCeStrategySeeder extends Seeder
{
    public function run(): void
    {
        $strategy = Strategy::firstOrCreate(
            ['user_id' => null, 'name' => 'VSS Chandelier RSI-MA'],
            [
                'description' => 'Chandelier Exit flip in the direction of the VSS multi-EMA trend stack (8 > 20 > 50, bias vs EMA 200), confirmed by RSI vs its moving average. A clean VSS High/Low channel breakout lifts the grade to A+. Decision support only — not financial advice.',
                'timeframes' => ['5m', '15m', '1h'],
                'active' => true,
            ]
        );

        // Rebuild rules each run so the set is always complete/idempotent.
        $strategy->rules()->delete();

        $rules = [
            // BUY CALL
            ['buy_call', 'ema_crossover', 'Chandelier Exit flips long & VSS stack bullish (EMA 8 > 20 > 50)'],
            ['buy_call', 'rsi', 'RSI above its moving average'],
            ['buy_call', 'htf', 'Price above EMA 200 (VSS long-term bias)'],
            ['buy_call', 'vwap', 'Price above VWAP'],
            ['buy_call', 'volume', 'Volume above average'],
            ['buy_call', 'sr', 'Clean breakout above the VSS High/Low channel'],
            // BUY PUT
            ['buy_put', 'ema_crossover', 'Chandelier Exit flips short & VSS stack bearish (EMA 8 < 20 < 50)'],
            ['buy_put', 'rsi', 'RSI below its moving average'],
            ['buy_put', 'htf', 'Price below EMA 200 (VSS long-term bias)'],
            ['buy_put', 'vwap', 'Price below VWAP'],
            ['buy_put', 'volume', 'Volume above average'],
            ['buy_put', 'sr', 'Clean breakdown below the VSS High/Low channel'],
            // EXIT
            ['exit', null, 'Chandelier Exit flips against the position'],
            ['exit', null, 'RSI crosses back through its moving average'],
            ['exit', null, 'Target or stop reached'],
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
