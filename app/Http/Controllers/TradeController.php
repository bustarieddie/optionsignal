<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTradeRequest;
use App\Http\Requests\UpdateTradeRequest;
use App\Models\Trade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TradeController extends Controller
{
    public function index(Request $request): View
    {
        $trades = $request->user()->trades()
            ->latest('opened_at')
            ->paginate(20);

        $closed = $request->user()->trades()->where('status', 'closed');
        $wins = (clone $closed)->where('pnl', '>', 0)->count();
        $losses = (clone $closed)->where('pnl', '<', 0)->count();
        $closedCount = (clone $closed)->count();
        $netPnl = (float) $request->user()->trades()->sum('pnl');

        $summary = [
            'wins' => $wins,
            'losses' => $losses,
            'closed' => $closedCount,
            'open' => $request->user()->trades()->where('status', 'open')->count(),
            'win_rate' => $closedCount > 0 ? round($wins / $closedCount * 100, 1) : 0.0,
            'net_pnl' => $netPnl,
        ];

        return view('content.osp.trades.index', compact('trades', 'summary'));
    }

    public function create(Request $request): View
    {
        $signal = null;
        $trade = new Trade(['direction' => 'call', 'quantity' => 1, 'opened_at' => now()]);

        // Pre-fill from a signal when launched via "Log Trade" on a signal.
        if ($signalId = $request->query('signal')) {
            $signal = $request->user()->signals()->with('strategy')->find($signalId);

            if ($signal) {
                $trade = new Trade([
                    'signal_id' => $signal->id,
                    'ticker' => $signal->ticker,
                    'direction' => $signal->signal_type === 'buy_put' ? 'put' : 'call',
                    'quantity' => 1,
                    'entry_price' => $signal->price,
                    'signal_grade' => $signal->grade,
                    'setup_name' => $signal->strategy?->name,
                    'opened_at' => now(),
                ]);
            }
        }

        return view('content.osp.trades.create', compact('trade', 'signal'));
    }

    public function store(StoreTradeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $status = 'open';
        $pnl = null;

        if (! empty($data['exit_price']) && ! empty($data['closed_at'])) {
            $status = 'closed';
            $pnl = $this->computePnl(
                $data['direction'],
                (float) $data['entry_price'],
                (float) $data['exit_price'],
                (int) $data['quantity']
            );
        }

        $trade = $request->user()->trades()->create([
            'signal_id' => $data['signal_id'] ?? null,
            'ticker' => $data['ticker'],
            'direction' => $data['direction'],
            'contract_details' => $data['contract_details'] ?? null,
            'setup_name' => $data['setup_name'] ?? null,
            'signal_grade' => $data['signal_grade'] ?? null,
            'entry_price' => $data['entry_price'],
            'exit_price' => $data['exit_price'] ?? null,
            'quantity' => $data['quantity'],
            'status' => $status,
            'pnl' => $pnl,
            'reason_for_entry' => $data['reason_for_entry'] ?? null,
            'opened_at' => $data['opened_at'],
            'closed_at' => $data['closed_at'] ?? null,
        ]);

        $this->handleScreenshot($request, $trade);

        return redirect()->route('trades.show', $trade)->with('status', 'Trade logged.');
    }

    public function show(Request $request, Trade $trade): View
    {
        abort_unless($trade->user_id === $request->user()->id, 403);

        $trade->load(['notes.user', 'screenshots', 'signal']);

        return view('content.osp.trades.show', compact('trade'));
    }

    public function edit(Request $request, Trade $trade): View
    {
        abort_unless($trade->user_id === $request->user()->id, 403);

        return view('content.osp.trades.edit', compact('trade'));
    }

    public function update(UpdateTradeRequest $request, Trade $trade): RedirectResponse
    {
        abort_unless($trade->user_id === $request->user()->id, 403);

        $data = $request->validated();

        $pnl = $trade->pnl;

        if ($data['status'] === 'closed' && ! empty($data['exit_price'])) {
            $pnl = array_key_exists('pnl', $data) && $data['pnl'] !== null
                ? (float) $data['pnl']
                : $this->computePnl(
                    $data['direction'],
                    (float) $data['entry_price'],
                    (float) $data['exit_price'],
                    (int) $data['quantity']
                );
        } elseif ($data['status'] !== 'closed') {
            $pnl = null;
        }

        $trade->update([
            'ticker' => $data['ticker'],
            'direction' => $data['direction'],
            'contract_details' => $data['contract_details'] ?? null,
            'setup_name' => $data['setup_name'] ?? null,
            'signal_grade' => $data['signal_grade'] ?? null,
            'entry_price' => $data['entry_price'],
            'exit_price' => $data['exit_price'] ?? null,
            'quantity' => $data['quantity'],
            'status' => $data['status'],
            'pnl' => $pnl,
            'reason_for_entry' => $data['reason_for_entry'] ?? null,
            'reason_for_exit' => $data['reason_for_exit'] ?? null,
            'mistake_notes' => $data['mistake_notes'] ?? null,
            'lessons' => $data['lessons'] ?? null,
            'emotion_score' => $data['emotion_score'] ?? null,
            'opened_at' => $data['opened_at'],
            'closed_at' => $data['closed_at'] ?? null,
        ]);

        $this->handleScreenshot($request, $trade);

        return redirect()->route('trades.show', $trade)->with('status', 'Trade updated.');
    }

    public function destroy(Request $request, Trade $trade): RedirectResponse
    {
        abort_unless($trade->user_id === $request->user()->id, 403);

        $trade->delete();

        return redirect()->route('trades.index')->with('status', 'Trade deleted.');
    }

    public function storeNote(Request $request, Trade $trade): RedirectResponse
    {
        abort_unless($trade->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $trade->notes()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'source' => 'user',
        ]);

        return redirect()->route('trades.show', $trade)->with('status', 'Note added.');
    }

    /**
     * Compute option P/L. Calls profit when price rises, puts when it falls.
     * Each contract represents 100 shares.
     */
    protected function computePnl(string $direction, float $entry, float $exit, int $quantity): float
    {
        $perContract = $direction === 'call'
            ? ($exit - $entry)
            : ($entry - $exit);

        return round($perContract * $quantity * 100, 2);
    }

    /**
     * Store an optional screenshot upload against the trade (polymorphic).
     */
    protected function handleScreenshot(Request $request, Trade $trade): void
    {
        if (! $request->hasFile('screenshot')) {
            return;
        }

        $path = $request->file('screenshot')->store("screenshots/{$trade->id}", 'public');

        $trade->screenshots()->create([
            'path' => $path,
            'caption' => $request->input('caption'),
        ]);
    }
}
