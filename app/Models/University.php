<?php

namespace App\Models;

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

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    public function admissionMethods(): HasMany
    {
        return $this->hasMany(AdmissionMethod::class);
    }
}