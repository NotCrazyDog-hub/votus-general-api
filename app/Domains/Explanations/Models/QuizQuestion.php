<?php

namespace App\Domains\Explanations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $fillable = [
        'explanation_id',
        'question',
        'explanation',
        'position',
        'based_on_content_version',
    ];

    /**
     * Cada registro guarda a referência para um único `Explanation`; vários registros podem compartilhar esse mesmo registro.
     */
    public function explanation(): BelongsTo
    {
        return $this->belongsTo(Explanation::class);
    }

    /**
     * Relação um-para-muitos com o modelo `QuizOption`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class)->orderBy('position');
    }
}
