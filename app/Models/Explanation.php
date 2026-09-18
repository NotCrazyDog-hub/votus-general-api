<?php

namespace App\Models;

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

    public function sources(): HasMany
    {
        return $this->hasMany(ExplanationSource::class);
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }
}
