<?php

namespace App\Domains\Opportunities\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicOpportunityResource extends JsonResource
{
    /**
     * Converte PublicOpportunity para a resposta JSON da API. Expõe os campos: source_key, type,
     * title, notice_number, agency, municipality, state, positions, education_levels, vacancies,
     * salary_min, salary_max, registration_start, registration_end, exam_date, fee_min, fee_max,
     * registration_url, summary, status, publications. As relações aparecem apenas quando o
     * controlador/serviço já as carregou, evitando consultas adicionais durante a serialização.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Chave estável para uso na URL do front,
            // em vez do id interno.
            'source_key' => $this->source_key,

            'type' => $this->type,
            'title' => $this->title,
            'notice_number' => $this->notice_number,
            'agency' => $this->agency,
            'municipality' => $this->municipality,
            'state' => $this->state,

            'positions' => $this->positions ?? [],
            'education_levels' => $this->education_levels ?? [],
            'vacancies' => $this->vacancies,

            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,

            'registration_start' => $this->registration_start?->toDateString(),
            'registration_end' => $this->registration_end?->toDateString(),
            'exam_date' => $this->exam_date?->toDateString(),

            'fee_min' => $this->fee_min,
            'fee_max' => $this->fee_max,

            'registration_url' => $this->registration_url,
            'summary' => $this->summary,

            // 'aberto' | 'em_breve' | 'encerrado' | 'indefinido'
            'status' => $this->status,

            'publications' => OpportunityPublicationResource::collection(
                $this->whenLoaded('publications')
            ),
        ];
    }
}