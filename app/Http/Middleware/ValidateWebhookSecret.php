<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the shared secret carried INSIDE the TradingView webhook JSON body
 * (TradingView cannot send custom auth headers). Uses a timing-safe compare.
 */
class ValidateWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        // TradingView posts the alert message as the body but often WITHOUT a
        // application/json content-type, so Laravel doesn't auto-parse it.
        // If the parsed input is empty, decode the raw body and merge it in.
        if (empty($request->all())) {
            $raw = trim((string) $request->getContent());
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $request->merge($decoded);
                }
            }
        }

        $expected = (string) config('services.tradingview.webhook_secret');
        $provided = (string) $request->input('secret', '');
        $valid = $expected !== '' && hash_equals($expected, $provided);

        // Inbound webhook audit — helps diagnose alerts that don't show up.
        // Logged at `warning` by default so it surfaces even when production runs
        // at LOG_LEVEL=warning; override via TRADINGVIEW_WEBHOOK_LOG_LEVEL.
        $level = (string) config('services.tradingview.webhook_log_level', 'warning');
        \Illuminate\Support\Facades\Log::log($level, 'TradingView webhook received', [
            'ip' => $request->ip(),
            'ticker' => $request->input('ticker'),
            'signal' => $request->input('signal'),
            'secret_valid' => $valid,
            'has_body' => ! empty($request->all()),
        ]);

        if (! $valid) {
            return response()->json(['message' => 'Invalid webhook secret.'], 401);
        }

        return $next($request);
    }
}
