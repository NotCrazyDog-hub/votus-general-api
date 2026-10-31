<?php

namespace App\Domains\Elections\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Domains\Legislatures\Http\Resources\LegislatorSummaryResource;

class CandidateResource extends JsonResource
{
    /**
     * Converte o candidato para o contrato público da API. Campos calculados só aparecem quando o
     * serviço os carregou; relações aninhadas só são serializadas quando foram eager-loaded, evitando
     * consultas inesperadas.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->external_id,
            'ballot_number' => $this->ballot_number,
            'state' => $this->state,
            'office' => $this->office_name,
            'civil_name' => $this->civil_name,
            'ballot_name' => $this->ballot_name,
            'party' => [
                'acronym' => $this->party_acronym,
                'name' => $this->party_name,
            ],
            'education_level' => $this->education_level,
            'occupation' => $this->occupation,
            'race_color' => $this->race_color,
            'photo_url' => $this->photo_url,
            'proposal_document_url' => $this->proposal_document_url,
            'election_year' => $this->election_year,
            'judgment_status' => $this->judgment_status,
            // Só presente quando o Service anexou (CandidateService::findByOffice,
            // detalhe/show) e o candidato de fato declarou despesa — "total" é
            // CALCULADO pelo Votus a partir dos registros oficiais, não vem
            // pronto do TSE. Hoje só existe dado real pros candidatos do Ceará.
            'expenses_summary' => $this->when(isset($this->expenses_summary), fn () => $this->expenses_summary),
            'running_mates' => CandidateResource::collection($this->whenLoaded('runningMates')),
            'previous_mandates' => LegislatorSummaryResource::collection($this->whenLoaded('previousMandates')),
            'election_status' => $this->when(
                array_key_exists('election_status', $this->resource->getAttributes()),
                $this->election_status
            ),
            // Situação eleitoral já classificada (eleito, não eleito, 2º
            // turno, aguardando, não verificada) — ver ElectionResultStatus.
            'election_result' => $this->when(
                array_key_exists('election_result', $this->resource->getAttributes()),
                fn () => $this->election_result
            ),
            'candidacy_history' => $this->whenLoaded('candidacyHistory', function () {
                return $this->candidacyHistory->map(function ($history) {
                    return [
                        'id' => $history->candidacy_external_id,
                        'election_year' => $history->election_year,
                        'round' => $history->round,
                        'state' => $history->state,
                        'office' => $history->office_name,
                        'ballot_number' => $history->ballot_number,

                        'party' => [
                            'acronym' => $history->party_acronym,
                            'name' => $history->party_name,
                        ],

                        'candidacy_status' => $history->candidacy_status,
                        'result_status' => $history->result_status,
                    ];
                });
            }),
        ];
    }
}
