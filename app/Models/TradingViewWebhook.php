<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingViewWebhook extends Model
{
    protected $table = 'tradingview_webhooks';

    protected $fillable = [
        'ticker', 'timeframe', 'signal', 'raw_payload', 'idempotency_hash',
        'source_ip', 'secret_valid', 'status', 'reject_reason', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'secret_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }

    /**
     * Raw payload with the shared secret stripped — safe for API/MCP exposure.
     *
     * @return array<string, mixed>
     */
    public function safePayload(): array
    {
        $payload = $this->raw_payload ?? [];
        unset($payload['secret']);

        return $payload;
    }
}
