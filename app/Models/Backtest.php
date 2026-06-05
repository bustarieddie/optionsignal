<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Backtest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'source', 'file_path', 'status', 'metrics', 'rows_count', 'error',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'rows_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(BacktestTrade::class);
    }
}
