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

        // Pinpoint *why* a request was rejected so blank-secret alerts (a common
        // cause of "alert fired but no signal") are obvious in the logs/response.
        $reason = match (true) {
            $valid => 'ok',
            $expected === '' => 'server_secret_not_configured',
            $provided === '' => 'secret_missing_from_payload',
            default => 'secret_mismatch',
        };

        // Inbound webhook audit — helps diagnose alerts that don't show up.
        // Logged at `warning` by default so it surfaces even when production runs
        // at LOG_LEVEL=warning; override via TRADINGVIEW_WEBHOOK_LOG_LEVEL.
        $level = (string) config('services.tradingview.webhook_log_level', 'warning');
        \Illuminate\Support\Facades\Log::log($level, 'TradingView webhook received', [
            'ip' => $request->ip(),
            'ticker' => $request->input('ticker'),
            'signal' => $request->input('signal'),
            'secret_valid' => $valid,
            'reason' => $reason,
            'has_body' => ! empty($request->all()),
        ]);

        if (! $valid) {
            $message = match ($reason) {
                'server_secret_not_configured' => 'Server webhook secret is not configured (set TRADINGVIEW_WEBHOOK_SECRET).',
                'secret_missing_from_payload' => 'Webhook secret missing from payload — paste it into the Pine script "Webhook secret" input.',
                default => 'Invalid webhook secret.',
            };

            return response()->json(['message' => $message], 401);
        }

        return $next($request);
    }
}
