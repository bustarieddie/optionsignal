<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestTrade extends Model
{
    protected $fillable = [
        'backtest_id', 'ticker', 'timeframe', 'direction',
        'entry', 'exit', 'pnl', 'grade', 'setup', 'result', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'entry' => 'decimal:4',
            'exit' => 'decimal:4',
            'pnl' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function backtest(): BelongsTo
    {
        return $this->belongsTo(Backtest::class);
    }
}
