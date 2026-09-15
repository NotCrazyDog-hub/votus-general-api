<?php

namespace App\Models;

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

    public function explanation()
    {
        return $this->belongsTo(Explanation::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class)
            ->orderBy('position');
    }
}