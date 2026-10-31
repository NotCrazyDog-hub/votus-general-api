<?php

namespace App\Domains\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    protected $fillable = [
        'university_id',
        'name',
        'ibge_city_code',
        'city',
        'normalized_city',
        'state',
        'region',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Cada registro guarda a referência para um único `University`; vários registros podem compartilhar esse mesmo registro.
     */
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    /**
     * Relação um-para-muitos com o modelo `CourseOffering`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}