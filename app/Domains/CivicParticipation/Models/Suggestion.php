<?php

namespace App\Domains\CivicParticipation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Suggestion extends Model
{
    protected $fillable = [
        'name',
        'email',
        'message',
    ];

    /**
     * Relação um-para-muitos com o modelo `SuggestionAnswer`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SuggestionAnswer::class);
    }
}
