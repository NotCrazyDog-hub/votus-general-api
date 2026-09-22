<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCurriculum extends Model
{
    protected $table = 'course_curricula';

    protected $fillable = [
        'course_offering_id',
        'name',
        'version_year',
        'total_hours',
        'duration_semesters',
        'official_url',
        'active',
        'verified_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'verified_at' => 'datetime',
        'version_year' => 'integer',
        'total_hours' => 'integer',
        'duration_semesters' => 'integer',
    ];

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(
            CourseOffering::class,
            'course_offering_id'
        );
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(
            CurriculumSubject::class,
            'course_curriculum_id'
        )
            ->orderBy('semester')
            ->orderBy('position');
    }
}