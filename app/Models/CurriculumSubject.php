<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumSubject extends Model
{
    protected $fillable = [
        'course_curriculum_id',
        'name',
        'semester',
        'workload_hours',
        'type',
        'position',
    ];

    protected $casts = [
        'semester' => 'integer',
        'workload_hours' => 'integer',
        'position' => 'integer',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(
            CourseCurriculum::class,
            'course_curriculum_id'
        );
    }
}