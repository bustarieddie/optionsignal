<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebhookRequest;
use App\Jobs\ProcessTradingViewSignal;
use App\Models\TradingViewWebhook;
use Illuminate\Http\JsonResponse;

class TradingViewWebhookController extends Controller
{
    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Never persist the shared secret at rest.
        $raw = $request->except('secret');

        $bucket = (int) floor(now()->timestamp / max(1, (int) config('signals.dedupe_window_seconds', 60)));
        $hash = hash('sha256', implode('|', [
            $data['ticker'], $data['timeframe'], $data['signal'], $bucket,
        ]));

        $existing = TradingViewWebhook::where('idempotency_hash', $hash)->first();
        if ($existing) {
            return response()->json([
                'status' => 'duplicate',
                'message' => 'Duplicate signal ignored.',
                'webhook_id' => $existing->id,
            ], 200);
        }

        $webhook = TradingViewWebhook::create([
            'ticker' => $data['ticker'],
            'timeframe' => $data['timeframe'],
            'signal' => $data['signal'],
            'raw_payload' => $raw,
            'idempotency_hash' => $hash,
            'source_ip' => $request->ip(),
            'secret_valid' => true,
            'status' => 'received',
        ]);

        ProcessTradingViewSignal::dispatch($webhook->id);

        return response()->json([
            'status' => 'accepted',
            'webhook_id' => $webhook->id,
        ], 202);
    }
}
