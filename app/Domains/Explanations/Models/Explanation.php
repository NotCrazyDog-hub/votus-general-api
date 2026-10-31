<?php

namespace App\Domains\Explanations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Explanation extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'question_title',
        'category',
        'summary',
        'what_is',
        'purpose',
        'practical_role',
        'why_it_matters',
        'citizen_impact',
        'example',
        'status',
        'content_version',
        'generation_error',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'content_version' => 'integer',
    ];

    /**
     * Relação um-para-muitos com o modelo `ExplanationSource`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function sources(): HasMany
    {
        return $this->hasMany(ExplanationSource::class);
    }

    /**
     * Relação um-para-muitos com o modelo `QuizQuestion`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }
}
