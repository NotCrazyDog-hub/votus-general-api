<?php

namespace App\Domains\Legislatures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LegislatorSummaryResource extends JsonResource
{
    /**
     * Converte LegislatorSummary para a resposta JSON da API. Expõe os campos: id, chamber,
     * parliamentary_name, party, state, status, bills. As relações aparecem apenas quando o
     * controlador/serviço já as carregou, evitando consultas adicionais durante a serialização.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->external_id,
            'chamber' => $this->chamber,
            'parliamentary_name' => $this->parliamentary_name,
            'party' => $this->party,
            'state' => $this->state,
            'status' => $this->status,
            // Proposições do mandato anterior (ligadas por CPF, não por nome —
            // ver Candidate::previousMandates). Serializadas direto pelo
            // Model (não por BillResource, que não é usado em nenhum lugar e
            // tem um formato diferente) pra bater exatamente com o mesmo
            // formato já consumido por ProposicoesList/LegislativeTimeline no
            // perfil de Deputados/Senadores.
            'bills' => $this->whenLoaded('bills'),
        ];
    }
}