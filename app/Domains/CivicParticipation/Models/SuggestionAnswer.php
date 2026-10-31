<?php

namespace App\Domains\CivicParticipation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionAnswer extends Model
{
    protected $fillable = [
        'suggestion_id',
        'suggestion_question_id',
        'answer',
    ];

    /**
     * Cada registro guarda a referência para um único `Suggestion`; vários registros podem compartilhar esse mesmo registro.
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    /**
     * Cada registro guarda a referência para um único `SuggestionQuestion`; vários registros podem compartilhar esse mesmo registro.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SuggestionQuestion::class, 'suggestion_question_id');
    }
}
