<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Strategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'description', 'timeframes', 'active', 'webhook_secret',
    ];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'timeframes' => 'array',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(StrategyRule::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }
}
