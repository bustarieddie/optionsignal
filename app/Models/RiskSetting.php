<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskSetting extends Model
{
    protected $fillable = [
        'user_id', 'max_daily_loss', 'max_trades_per_day', 'risk_per_trade_pct',
        'max_position_size', 'stop_loss_pct', 'take_profit_pct',
        'cooldown_minutes_after_loss', 'no_trade_window_start', 'no_trade_window_end',
    ];

    protected function casts(): array
    {
        return [
            'max_daily_loss' => 'decimal:2',
            'risk_per_trade_pct' => 'decimal:2',
            'max_position_size' => 'decimal:2',
            'stop_loss_pct' => 'decimal:2',
            'take_profit_pct' => 'decimal:2',
            'max_trades_per_day' => 'integer',
            'cooldown_minutes_after_loss' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
