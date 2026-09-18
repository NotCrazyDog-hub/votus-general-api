<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExplanationSource extends Model
{
    protected $fillable = [
        'explanation_id',
        'trusted_source_id',
        'source_name',
        'source_url',
        'source_domain',
    ];

    public function explanation(): BelongsTo
    {
        return $this->belongsTo(Explanation::class);
    }
}
