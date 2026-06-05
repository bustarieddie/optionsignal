<?php

namespace App\Services\Signals;

use App\DataTransferObjects\WebhookPayload;
use App\Models\Strategy;

class StrategyMatcher
{
    /**
     * Resolve the strategy a webhook maps to. Prefers a user's own strategy
     * matching the payload's strategy name, then a system strategy by name,
     * then the system default.
     */
    public function match(WebhookPayload $payload, int $userId): ?Strategy
    {
        $name = $payload->strategy;

        if ($name) {
            $byName = Strategy::query()
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhereNull('user_id');
                })
                ->where('active', true)
                ->where(function ($q) use ($name) {
                    $q->where('name', $name)
                        ->orWhere('name', 'like', '%' . str_replace('_', '%', $name) . '%');
                })
                ->orderByRaw('user_id IS NULL') // user strategy before system
                ->first();

            if ($byName) {
                return $byName;
            }
        }

        // Fall back to the system default strategy.
        return Strategy::whereNull('user_id')->where('active', true)->orderBy('id')->first();
    }
}
