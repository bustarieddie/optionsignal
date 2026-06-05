<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeNote extends Model
{
    protected $fillable = ['trade_id', 'user_id', 'body', 'source'];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
