<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $trades = Trade::where('user_id', $request->user()->id)
            ->latest('opened_at')
            ->paginate(25);

        return response()->json($trades);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticker' => ['required', 'string', 'max:10'],
            'direction' => ['required', 'in:call,put'],
            'entry_price' => ['nullable', 'numeric'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'signal_id' => ['nullable', 'integer', 'exists:signals,id'],
            'strategy_id' => ['nullable', 'integer', 'exists:strategies,id'],
            'setup_name' => ['nullable', 'string', 'max:120'],
            'reason_for_entry' => ['nullable', 'string'],
        ]);

        $trade = $request->user()->trades()->create(array_merge($data, [
            'ticker' => strtoupper($data['ticker']),
            'status' => 'open',
            'quantity' => $data['quantity'] ?? 1,
            'opened_at' => now(),
        ]));

        return response()->json(['data' => $trade], 201);
    }
}
