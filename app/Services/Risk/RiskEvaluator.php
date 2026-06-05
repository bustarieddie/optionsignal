<?php

namespace App\Services\Risk;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Evaluates whether a new trade idea is within a user's configured risk limits.
 * Returns a result with allowed flag and human-readable reasons. This never
 * blocks signals from being saved — it annotates them for decision support.
 */
class RiskEvaluator
{
    /**
     * @return array{allowed: bool, reasons: array<int, string>}
     */
    public function evaluate(User $user): array
    {
        $settings = $user->riskSetting;
        $reasons = [];

        if (! $settings) {
            return ['allowed' => true, 'reasons' => []];
        }

        $today = Carbon::today();

        // Max trades per day
        $tradesToday = Trade::where('user_id', $user->id)
            ->whereDate('opened_at', $today)
            ->count();
        if ($tradesToday >= $settings->max_trades_per_day) {
            $reasons[] = "Daily trade limit reached ({$settings->max_trades_per_day}).";
        }

        // Max daily loss (as a positive % threshold against today's realised loss)
        $dailyPnl = (float) Trade::where('user_id', $user->id)
            ->whereDate('closed_at', $today)
            ->sum('pnl');
        if ($dailyPnl < 0 && abs($dailyPnl) >= (float) $settings->max_daily_loss) {
            $reasons[] = 'Max daily loss threshold reached.';
        }

        // Cooldown after a loss
        $lastLoss = Trade::where('user_id', $user->id)
            ->where('pnl', '<', 0)
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->first();
        if ($lastLoss && $lastLoss->closed_at
            && $lastLoss->closed_at->diffInMinutes(now()) < $settings->cooldown_minutes_after_loss) {
            $reasons[] = "In post-loss cooldown ({$settings->cooldown_minutes_after_loss} min).";
        }

        // No-trade time window
        if ($settings->no_trade_window_start && $settings->no_trade_window_end) {
            $now = now()->format('H:i');
            if ($now >= $settings->no_trade_window_start && $now <= $settings->no_trade_window_end) {
                $reasons[] = "Within no-trade window ({$settings->no_trade_window_start}–{$settings->no_trade_window_end}).";
            }
        }

        return ['allowed' => count($reasons) === 0, 'reasons' => $reasons];
    }
}
