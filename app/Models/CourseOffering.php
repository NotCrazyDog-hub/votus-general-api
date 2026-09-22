<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends Model
{
    protected $fillable = [
        'campus_id',
        'mec_course_code',
        'name',
        'normalized_name',
        'degree',
        'area',
        'modality',
        'status',
        'authorized_vacancies',
        'workload_hours',
        'source_name',
        'source_updated_at',
    ];

    protected $casts = [
        'source_updated_at' => 'date',
        'authorized_vacancies' => 'integer',
        'workload_hours' => 'integer',
    ];

    /**
     * Campus ou local onde o curso é oferecido.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Matrizes curriculares cadastradas para esta oferta.
     */
    public function curricula(): HasMany
    {
        return $this->hasMany(
            CourseCurriculum::class,
            'course_offering_id'
        );
    }
}