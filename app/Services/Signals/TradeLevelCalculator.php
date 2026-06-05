<?php

namespace App\Services\Signals;

/**
 * Computes decision-support entry / stop / take-profit levels on the underlying.
 * ATR-based when an ATR value is supplied, otherwise a percentage of entry.
 * Direction-aware: calls target upside, puts target downside.
 */
class TradeLevelCalculator
{
    /**
     * @param  string  $direction  'call' | 'put'
     * @return array{entry: float, stop_loss: float, tp1: float, tp2: float, tp3: float}|null
     */
    public function compute(string $direction, ?float $entry, ?float $atr = null): ?array
    {
        if ($entry === null || $entry <= 0) {
            return null;
        }

        $cfg = config('signals.levels');

        // Distance for each leg (ATR multiple, or % of entry as fallback).
        $slDist  = $atr ? $cfg['sl_atr'] * $atr  : $entry * $cfg['sl_pct'] / 100;
        $tp1Dist = $atr ? $cfg['tp1_atr'] * $atr : $entry * $cfg['tp1_pct'] / 100;
        $tp2Dist = $atr ? $cfg['tp2_atr'] * $atr : $entry * $cfg['tp2_pct'] / 100;
        $tp3Dist = $atr ? $cfg['tp3_atr'] * $atr : $entry * $cfg['tp3_pct'] / 100;

        $up = $direction === 'call';

        return [
            'entry' => round($entry, 4),
            'stop_loss' => round($up ? $entry - $slDist : $entry + $slDist, 4),
            'tp1' => round($up ? $entry + $tp1Dist : $entry - $tp1Dist, 4),
            'tp2' => round($up ? $entry + $tp2Dist : $entry - $tp2Dist, 4),
            'tp3' => round($up ? $entry + $tp3Dist : $entry - $tp3Dist, 4),
        ];
    }
}
