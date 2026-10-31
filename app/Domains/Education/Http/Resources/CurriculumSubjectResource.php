<?php

namespace App\Domains\Education\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumSubjectResource extends JsonResource
{
    /**
     * Converte CurriculumSubject para a resposta JSON da API. Expõe os campos: name, semester,
     * workload_hours, type. @return array<string, mixed>
     */
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