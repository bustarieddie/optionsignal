<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalScore extends Model
{
    protected $fillable = ['signal_id', 'component', 'points', 'detail'];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'points' => 'integer',
        ];
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }
}
