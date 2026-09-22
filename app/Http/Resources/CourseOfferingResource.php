<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseOfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Não há um identificador externo estável aqui:
            // mec_course_code se repete entre campi diferentes.
            'id' => $this->id,

            'mec_course_code' => $this->mec_course_code,
            'name' => $this->name,
            'degree' => $this->degree,
            'area' => $this->area,
            'modality' => $this->modality,
            'status' => $this->status,
            'authorized_vacancies' => $this->authorized_vacancies,
            'workload_hours' => $this->workload_hours,
            'source_name' => $this->source_name,
            'source_updated_at' => $this->source_updated_at?->toDateString(),

            'campus' => new CampusResource(
                $this->whenLoaded('campus')
            ),

            'curricula' => CourseCurriculumResource::collection(
                $this->whenLoaded('curricula')
            ),
        ];
    }
}