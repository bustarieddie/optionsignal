<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionSuggestion extends Model
{
    protected $fillable = [
        'signal_id', 'contract_type',
        'suggested_delta_min', 'suggested_delta_max', 'suggested_expiry',
        'spread_note', 'liquidity_note', 'risk_note',
    ];

    protected function casts(): array
    {
        return [
            'suggested_delta_min' => 'decimal:2',
            'suggested_delta_max' => 'decimal:2',
        ];
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }
}
