<?php

namespace App\Services\Signals;

use App\DataTransferObjects\ScoreBreakdown;
use App\DataTransferObjects\WebhookPayload;

/**
 * Computes the additive confidence score for an incoming signal using the
 * weights in config/signals.php. Direction-aware: call vs put invert the
 * directional checks (EMA/RSI/VWAP/HTF).
 */
class ConfidenceScorer
{
    public function score(WebhookPayload $payload): ScoreBreakdown
    {
        $weights = config('signals.weights');
        $call = $payload->isCall();
        $components = [];
        $total = 0;

        // 1. EMA crossover
        $emaOk = $payload->ema9 !== null && $payload->ema21 !== null
            && ($call ? $payload->ema9 > $payload->ema21 : $payload->ema9 < $payload->ema21);
        $total += $this->push($components, 'ema_crossover', $emaOk ? $weights['ema_crossover'] : 0, [
            'ema9' => $payload->ema9, 'ema21' => $payload->ema21,
        ]);

        // 2. RSI vs RSI MA
        $rsiOk = $payload->rsi !== null && $payload->rsiMa !== null
            && ($call ? $payload->rsi > $payload->rsiMa : $payload->rsi < $payload->rsiMa);
        $total += $this->push($components, 'rsi', $rsiOk ? $weights['rsi'] : 0, [
            'rsi' => $payload->rsi, 'rsi_ma' => $payload->rsiMa,
        ]);

        // 3. VWAP alignment
        $vwapOk = $payload->price !== null && $payload->vwap !== null
            && ($call ? $payload->price > $payload->vwap : $payload->price < $payload->vwap);
        $total += $this->push($components, 'vwap', $vwapOk ? $weights['vwap'] : 0, [
            'price' => $payload->price, 'vwap' => $payload->vwap,
        ]);

        // 4. Volume confirmation
        $volOk = in_array($payload->volumeStatus, ['above_average', 'above'], true);
        $total += $this->push($components, 'volume', $volOk ? $weights['volume'] : 0, [
            'volume_status' => $payload->volumeStatus,
        ]);

        // 5. Higher-timeframe alignment (optional field)
        $htfOk = $payload->htfTrend !== null
            && ($call ? $payload->htfTrend === 'bullish' : $payload->htfTrend === 'bearish');
        $total += $this->push($components, 'htf', $htfOk ? $weights['htf'] : 0, [
            'htf_trend' => $payload->htfTrend,
        ]);

        // 6. Clean support/resistance (optional field)
        $srOk = $payload->srClear === true;
        $total += $this->push($components, 'sr', $srOk ? $weights['sr'] : 0, [
            'sr_clear' => $payload->srClear,
        ]);

        return new ScoreBreakdown($components, $total);
    }

    /**
     * @param  array<int, array>  $components
     */
    private function push(array &$components, string $component, int $points, array $detail): int
    {
        $components[] = ['component' => $component, 'points' => $points, 'detail' => $detail];

        return $points;
    }
}
