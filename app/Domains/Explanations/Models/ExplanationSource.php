<?php

namespace App\Domains\Explanations\Models;

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

    /**
     * Cada registro guarda a referência para um único `Explanation`; vários registros podem compartilhar esse mesmo registro.
     */
    public function explanation(): BelongsTo
    {
        return $this->belongsTo(Explanation::class);
    }
}
