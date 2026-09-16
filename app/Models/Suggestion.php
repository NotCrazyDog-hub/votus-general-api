<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Suggestion extends Model
{
    protected $fillable = [
        'name',
        'email',
        'message',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(SuggestionAnswer::class);
    }
}
