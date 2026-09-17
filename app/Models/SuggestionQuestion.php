<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuggestionQuestion extends Model
{
    protected $fillable = [
        'text',
        'type',
        'options',
        'required',
        'order_index',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'order_index' => 'integer',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(SuggestionAnswer::class);
    }
}
