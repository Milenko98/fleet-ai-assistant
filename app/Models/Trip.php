<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['datum' => 'date', 'km' => 'float', 'gorivo_l' => 'float'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
