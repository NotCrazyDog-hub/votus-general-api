<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCurriculumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'version_year' => $this->version_year,
            'total_hours' => $this->total_hours,
            'duration_semesters' => $this->duration_semesters,
            'official_url' => $this->official_url,

            'subjects' => CurriculumSubjectResource::collection(
                $this->whenLoaded('subjects')
            ),
        ];
    }
}