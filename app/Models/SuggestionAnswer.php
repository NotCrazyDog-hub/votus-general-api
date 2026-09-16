<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionAnswer extends Model
{
    protected $fillable = [
        'suggestion_id',
        'suggestion_question_id',
        'answer',
    ];

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SuggestionQuestion::class, 'suggestion_question_id');
    }
}
