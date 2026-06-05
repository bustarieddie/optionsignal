<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use App\Models\Trade;
use App\Services\Analytics\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly PerformanceService $performance)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $stats = $this->performance->dashboardSummary($user);
        $charts = $this->performance->dashboardCharts($user);

        $latestSignals = Signal::where('user_id', $user->id)
            ->latest('occurred_at')
            ->limit(8)
            ->get();

        $activeIdeas = Signal::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('grade', ['A+', 'A', 'B'])
            ->latest('occurred_at')
            ->limit(6)
            ->get();

        $openTrades = Trade::where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->limit(6)
            ->get();

        $watchlist = $user->watchlists()->where('active', true)->orderBy('ticker')->get();

        return view('content.osp.dashboard.index', compact(
            'stats', 'charts', 'latestSignals', 'activeIdeas', 'openTrades', 'watchlist'
        ));
    }
}
