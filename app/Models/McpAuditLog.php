<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'token_id', 'tool_name', 'is_write',
        'arguments', 'result_status', 'duration_ms', 'source_ip', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_write' => 'boolean',
            'arguments' => 'array',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
