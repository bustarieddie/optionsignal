<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Screenshot extends Model
{
    protected $fillable = ['imageable_type', 'imageable_id', 'path', 'thumb_path', 'caption'];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
