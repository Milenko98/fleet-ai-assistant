<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    // embedding i tsv se čitaju/pišu kroz raw SQL (pgvector), ne kroz Eloquent
    protected $hidden = ['embedding', 'tsv'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
