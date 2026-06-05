<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_id', 'rule_type', 'component',
        'condition_key', 'operator', 'value', 'points', 'sort',
    ];

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }
}
