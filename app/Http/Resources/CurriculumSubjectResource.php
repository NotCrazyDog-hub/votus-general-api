<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumSubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'semester' => $this->semester,
            'workload_hours' => $this->workload_hours,
            'type' => $this->type,
        ];
    }
}