<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Signal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'strategy_id', 'watchlist_id', 'tradingview_webhook_id',
        'ticker', 'timeframe', 'signal_type', 'price',
        'ema9', 'ema21', 'rsi', 'rsi_ma', 'vwap', 'volume_status', 'rs_status',
        'atr', 'stop_loss', 'tp1', 'tp2', 'tp3',
        'grade', 'total_score', 'status', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'ema9' => 'decimal:4',
            'ema21' => 'decimal:4',
            'rsi' => 'decimal:4',
            'rsi_ma' => 'decimal:4',
            'vwap' => 'decimal:4',
            'atr' => 'decimal:4',
            'stop_loss' => 'decimal:4',
            'tp1' => 'decimal:4',
            'tp2' => 'decimal:4',
            'tp3' => 'decimal:4',
            'total_score' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(Watchlist::class);
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(TradingViewWebhook::class, 'tradingview_webhook_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SignalScore::class);
    }

    public function optionSuggestion(): HasOne
    {
        return $this->hasOne(OptionSuggestion::class);
    }

    public function screenshots(): MorphMany
    {
        return $this->morphMany(Screenshot::class, 'imageable');
    }

    /**
     * Relative-strength badge for the UI: [badgeClass, shortLabel, tooltip].
     * Handles both the combined (leading_both/lagging_both/mixed) and the
     * legacy single-benchmark (outperforming/lagging/inline) vocabularies.
     */
    public function rsBadge(): ?array
    {
        return match ($this->rs_status) {
            'leading_both', 'outperforming' => ['bg-label-success', 'RS ↑', 'Leading QQQ & SPY'],
            'lagging_both', 'lagging' => ['bg-label-danger', 'RS ↓', 'Lagging QQQ & SPY'],
            'mixed', 'inline' => ['bg-label-secondary', 'RS ~', 'Mixed vs QQQ / SPY'],
            default => null,
        };
    }

    /** Colour code used in the UI: green=call, red=put, grey=ignored, yellow=watch. */
    public function colorCode(): string
    {
        if ($this->grade === 'ignore') {
            return 'grey';
        }

        return match ($this->signal_type) {
            'buy_call' => 'green',
            'buy_put' => 'red',
            default => 'yellow',
        };
    }
}
