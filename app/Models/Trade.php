<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'signal_id', 'strategy_id', 'ticker', 'direction',
        'contract_details', 'setup_name', 'signal_grade',
        'entry_price', 'exit_price', 'quantity', 'status', 'pnl',
        'reason_for_entry', 'reason_for_exit', 'mistake_notes', 'lessons',
        'emotion_score', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_price' => 'decimal:4',
            'exit_price' => 'decimal:4',
            'pnl' => 'decimal:2',
            'quantity' => 'integer',
            'emotion_score' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TradeNote::class);
    }

    public function screenshots(): MorphMany
    {
        return $this->morphMany(Screenshot::class, 'imageable');
    }

    public function isWin(): bool
    {
        return $this->pnl !== null && (float) $this->pnl > 0;
    }
}
