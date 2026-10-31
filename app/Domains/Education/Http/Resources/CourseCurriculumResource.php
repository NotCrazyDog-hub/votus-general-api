<?php

namespace App\Domains\Education\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCurriculumResource extends JsonResource
{
    /**
     * Converte CourseCurriculum para a resposta JSON da API. Expõe os campos: name, version_year,
     * total_hours, duration_semesters, official_url, subjects. As relações aparecem apenas quando o
     * controlador/serviço já as carregou, evitando consultas adicionais durante a serialização.
     *
     * @return array<string, mixed>
     */
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