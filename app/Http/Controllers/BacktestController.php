<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBacktestRequest;
use App\Jobs\ProcessBacktestImport;
use App\Models\Backtest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BacktestController extends Controller
{
    public function index(Request $request): View
    {
        $backtests = $request->user()->backtests()
            ->latest()
            ->paginate(20);

        return view('content.osp.backtests.index', compact('backtests'));
    }

    public function create(): View
    {
        return view('content.osp.backtests.create');
    }

    public function store(StoreBacktestRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $path = $request->file('file')->store("backtests/{$request->user()->id}", 'local');

        $backtest = $request->user()->backtests()->create([
            'name' => $data['name'],
            'source' => 'csv',
            'file_path' => $path,
            'status' => 'pending',
            'rows_count' => 0,
        ]);

        ProcessBacktestImport::dispatch($backtest->id);

        return redirect()->route('backtests.show', $backtest)
            ->with('status', 'Processing… refresh in a moment to see results.');
    }

    public function show(Request $request, Backtest $backtest): View
    {
        abort_unless($backtest->user_id === $request->user()->id, 403);

        $backtest->load('trades');

        return view('content.osp.backtests.show', compact('backtest'));
    }

    public function destroy(Request $request, Backtest $backtest): RedirectResponse
    {
        abort_unless($backtest->user_id === $request->user()->id, 403);

        $backtest->delete();

        return redirect()->route('backtests.index')->with('status', 'Backtest deleted.');
    }
}
