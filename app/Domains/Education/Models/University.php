<?php

namespace App\Domains\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    protected $fillable = [
        'mec_code',
        'name',
        'acronym',
        'administrative_category',
        'academic_organization',
        'sector',
        'website',
    ];

    /**
     * Relação um-para-muitos com o modelo `Campus`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    /**
     * Relação um-para-muitos com o modelo `AdmissionMethod`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function admissionMethods(): HasMany
    {
        return $this->hasMany(AdmissionMethod::class);
    }

    /** Usa o código MEC como chave legível nas rotas em vez do ID interno. */
    public function getRouteKeyName(): string
    {
        return 'mec_code';
    }
}