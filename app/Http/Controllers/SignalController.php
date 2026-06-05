<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignalController extends Controller
{
    public function index(Request $request): View
    {
        $query = Signal::where('user_id', $request->user()->id)
            ->with('strategy')
            ->latest('occurred_at');

        if ($ticker = $request->query('ticker')) {
            $query->where('ticker', strtoupper($ticker));
        }
        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }
        if ($type = $request->query('type')) {
            $query->where('signal_type', $type);
        }

        $signals = $query->paginate(20)->withQueryString();
        $tickers = $request->user()->watchlists()->orderBy('ticker')->pluck('ticker');

        return view('content.osp.signals.index', compact('signals', 'tickers'));
    }

    public function show(Request $request, Signal $signal): View
    {
        abort_unless($signal->user_id === $request->user()->id, 403);

        $signal->load(['strategy', 'scores', 'optionSuggestion', 'webhook']);

        return view('content.osp.signals.show', compact('signal'));
    }
}
