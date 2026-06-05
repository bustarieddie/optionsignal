<?php

namespace App\Services\Analytics;

use App\Models\Signal;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Carbon;

class PerformanceService
{
    /**
     * High-level summary cards for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function dashboardSummary(User $user): array
    {
        $closed = Trade::where('user_id', $user->id)->where('status', 'closed')->get();
        $wins = $closed->filter(fn (Trade $t) => (float) $t->pnl > 0);
        $losses = $closed->filter(fn (Trade $t) => (float) $t->pnl < 0);

        $totalClosed = $closed->count();
        $winRate = $totalClosed > 0 ? round($wins->count() / $totalClosed * 100, 1) : 0.0;

        $dailyPl = Trade::where('user_id', $user->id)
            ->whereDate('closed_at', Carbon::today())
            ->sum('pnl');

        return [
            'open_trades' => Trade::where('user_id', $user->id)->where('status', 'open')->count(),
            'closed_trades' => $totalClosed,
            'wins' => $wins->count(),
            'losses' => $losses->count(),
            'win_rate' => $winRate,
            'net_pnl' => round((float) $closed->sum('pnl'), 2),
            'daily_pnl' => round((float) $dailyPl, 2),
            'signal_accuracy' => $this->signalAccuracy($user),
            'best_ticker' => $this->bestTicker($user),
            'active_signals' => Signal::where('user_id', $user->id)->where('status', 'active')->count(),
            'strategy_performance' => $this->strategyPerformance($user),
        ];
    }

    /**
     * Percentage of graded (actionable) signals that led to a winning trade.
     */
    public function signalAccuracy(User $user): float
    {
        $actionable = Signal::where('user_id', $user->id)
            ->where('grade', '!=', 'ignore')
            ->whereHas('strategy')
            ->count();

        if ($actionable === 0) {
            // Fall back to graded signal count vs trades won.
            $graded = Signal::where('user_id', $user->id)->where('grade', '!=', 'ignore')->count();
            if ($graded === 0) {
                return 0.0;
            }
        }

        $winningTradesFromSignals = Trade::where('user_id', $user->id)
            ->whereNotNull('signal_id')
            ->where('pnl', '>', 0)
            ->count();

        $tradesFromSignals = Trade::where('user_id', $user->id)
            ->whereNotNull('signal_id')
            ->where('status', 'closed')
            ->count();

        return $tradesFromSignals > 0
            ? round($winningTradesFromSignals / $tradesFromSignals * 100, 1)
            : 0.0;
    }

    public function bestTicker(User $user): ?string
    {
        return Trade::where('user_id', $user->id)
            ->where('status', 'closed')
            ->selectRaw('ticker, SUM(pnl) as total')
            ->groupBy('ticker')
            ->orderByDesc('total')
            ->value('ticker');
    }

    /**
     * Chart-ready datasets for the dashboard (ApexCharts).
     *
     * @return array<string, mixed>
     */
    public function dashboardCharts(User $user): array
    {
        // Equity curve — cumulative realised P/L over closed trades.
        $closed = Trade::where('user_id', $user->id)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->orderBy('closed_at')
            ->get(['pnl', 'closed_at']);

        $equityLabels = [];
        $equitySeries = [];
        $running = 0.0;
        foreach ($closed as $t) {
            $running += (float) $t->pnl;
            $equityLabels[] = optional($t->closed_at)->format('M j');
            $equitySeries[] = round($running, 2);
        }

        // Signal grade distribution.
        $grades = ['A+', 'A', 'B', 'C', 'ignore'];
        $gradeCounts = Signal::where('user_id', $user->id)
            ->selectRaw('grade, COUNT(*) as c')
            ->groupBy('grade')
            ->pluck('c', 'grade');

        return [
            'equity' => ['labels' => $equityLabels, 'series' => $equitySeries],
            'win_loss' => [
                'wins' => $closed->filter(fn (Trade $t) => (float) $t->pnl > 0)->count(),
                'losses' => $closed->filter(fn (Trade $t) => (float) $t->pnl < 0)->count(),
            ],
            'grades' => [
                'labels' => $grades,
                'series' => array_map(fn ($g) => (int) ($gradeCounts[$g] ?? 0), $grades),
            ],
        ];
    }

    /**
     * Per-strategy aggregate performance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function strategyPerformance(User $user): array
    {
        return Trade::where('user_id', $user->id)
            ->where('status', 'closed')
            ->whereNotNull('strategy_id')
            ->selectRaw('strategy_id, COUNT(*) as trades, SUM(pnl) as pnl, SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as wins')
            ->groupBy('strategy_id')
            ->with('strategy:id,name')
            ->get()
            ->map(fn ($row) => [
                'strategy' => $row->strategy?->name ?? 'Unknown',
                'trades' => (int) $row->trades,
                'pnl' => round((float) $row->pnl, 2),
                'win_rate' => $row->trades > 0 ? round($row->wins / $row->trades * 100, 1) : 0.0,
            ])
            ->all();
    }
}
