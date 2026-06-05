<?php

namespace App\Services\Backtest;

class MetricsCalculator
{
    /**
     * Compute summary metrics over a set of normalized trade rows.
     * Each row is expected to expose: pnl, ticker, timeframe, grade, setup.
     *
     * @param  iterable<int, array<string, mixed>>  $trades
     * @return array<string, mixed>
     */
    public function compute(iterable $trades): array
    {
        $total = 0;
        $wins = 0;
        $losses = 0;
        $grossProfit = 0.0;
        $grossLoss = 0.0; // stored as a positive magnitude

        $equity = 0.0;
        $peak = 0.0;
        $maxDrawdown = 0.0;

        $byTicker = [];
        $byTimeframe = [];
        $byGrade = [];
        $bySetup = [];

        foreach ($trades as $t) {
            $pnl = (float) ($t['pnl'] ?? 0);
            $total++;

            if ($pnl > 0) {
                $wins++;
                $grossProfit += $pnl;
            } elseif ($pnl < 0) {
                $losses++;
                $grossLoss += abs($pnl);
            }

            // Running equity for drawdown (single pass, peak-to-trough).
            $equity += $pnl;
            if ($equity > $peak) {
                $peak = $equity;
            }
            $drawdown = $peak - $equity;
            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
            }

            $this->accumulate($byTicker, $t['ticker'] ?? null, $pnl);
            $this->accumulate($byTimeframe, $t['timeframe'] ?? null, $pnl);
            $this->accumulate($byGrade, $t['grade'] ?? null, $pnl);
            $this->accumulate($bySetup, $t['setup'] ?? null, $pnl);
        }

        $winRate = $total > 0 ? $wins / $total : 0.0;
        $lossRate = $total > 0 ? $losses / $total : 0.0;

        $avgWin = $wins > 0 ? $grossProfit / $wins : 0.0;
        $avgLoss = $losses > 0 ? $grossLoss / $losses : 0.0;

        $profitFactor = $grossLoss > 0
            ? $grossProfit / $grossLoss
            : ($grossProfit > 0 ? null : 0.0); // null = no losses (undefined)

        $expectancy = ($winRate * $avgWin) - ($lossRate * $avgLoss);

        return [
            'total_trades' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => round($winRate * 100, 1),
            'avg_win' => round($avgWin, 2),
            'avg_loss' => round($avgLoss, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'profit_factor' => $profitFactor === null ? null : round($profitFactor, 2),
            'expectancy' => round($expectancy, 2),
            'max_drawdown' => round($maxDrawdown, 2),
            'best_ticker' => $this->bestKey($byTicker),
            'best_timeframe' => $this->bestKey($byTimeframe),
            'best_grade' => $this->bestKey($byGrade),
            'best_setup' => $this->bestKey($bySetup),
        ];
    }

    protected function accumulate(array &$bucket, mixed $key, float $pnl): void
    {
        if ($key === null || $key === '') {
            return;
        }

        $key = (string) $key;
        $bucket[$key] = ($bucket[$key] ?? 0.0) + $pnl;
    }

    protected function bestKey(array $bucket): ?string
    {
        if (empty($bucket)) {
            return null;
        }

        arsort($bucket);

        return (string) array_key_first($bucket);
    }
}
