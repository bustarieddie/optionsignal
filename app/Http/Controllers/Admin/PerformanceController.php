<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Signal;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    /**
     * Platform-wide signal performance aggregates across all users.
     */
    public function index(): View
    {
        $totalSignals = Signal::query()->count();

        $byGrade = Signal::query()
            ->selectRaw('grade, COUNT(*) as total')
            ->groupBy('grade')
            ->orderByDesc('total')
            ->pluck('total', 'grade')
            ->all();

        $byTicker = Signal::query()
            ->selectRaw('ticker, COUNT(*) as total')
            ->groupBy('ticker')
            ->orderByDesc('total')
            ->limit(15)
            ->pluck('total', 'ticker')
            ->all();

        // Actionable = anything not graded "ignore".
        $actionable = Signal::query()->where('grade', '!=', 'ignore')->count();
        $actionableRate = $totalSignals > 0
            ? round($actionable / $totalSignals * 100, 1)
            : 0.0;

        $stats = [
            'total_signals'   => $totalSignals,
            'actionable'      => $actionable,
            'actionable_rate' => $actionableRate,
            'distinct_tickers' => Signal::query()->distinct('ticker')->count('ticker'),
        ];

        return view('content.osp.admin.performance', compact('stats', 'byGrade', 'byTicker'));
    }
}
