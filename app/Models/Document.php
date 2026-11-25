<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $guarded = [];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('redni_broj');
    }
}
