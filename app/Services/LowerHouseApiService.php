<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Services\Concerns\NormalizesProfessionNames;

class LowerHouseApiService
{
    use NormalizesProfessionNames;
    protected string $baseUrl = 'https://dadosabertos.camara.leg.br/api/v2';

    public function listIds(): array
    {
        $response = Http::withOptions(['verify' => false])->get("{$this->baseUrl}/deputados", [
            'itens' => 100,
            'siglaUf' => 'CE',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch legislators list: ' . $response->status());
        }

        return collect($response->json('dados'))->pluck('id')->all();
    }

    public function getDetails(string $id): array
    {
        $response = Http::withOptions(['verify' => false])->get("{$this->baseUrl}/deputados/{$id}");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch details for legislator {$id}: " . $response->status());
        }

        return $response->json('dados');
    }

    public function getCommittees(string $id): array
    {
        $response = Http::withOptions(['verify' => false])->get("{$this->baseUrl}/deputados/{$id}/orgaos");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch committees for legislator {$id}: " . $response->status());
        }

        return $response->json('dados') ?? [];
    }

    public function getBillsByLegislator(string $id): array
    {
        $allBills = [];
        $page = 1;

        do {
            $query = http_build_query([
                'idDeputadoAutor' => $id,
                'itens' => 100,
                'pagina' => $page,
            ]) . '&siglaTipo=PL&siglaTipo=PEC';

            $response = Http::withOptions(['verify' => false])->get("{$this->baseUrl}/proposicoes?{$query}");

            if ($response->failed()) {
                throw new \RuntimeException("Failed to fetch bills for legislator {$id}: " . $response->status());
            }

            $data = $response->json('dados');
            $allBills = array_merge($allBills, $data);
            $page++;
        } while (count($data) === 100);

        return $allBills;
    }

    public function getBillStatus(string $billId): array
    {
        $response = Http::withOptions(['verify' => false])
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->baseUrl}/proposicoes/{$billId}");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch status for bill {$billId}: " . $response->status());
        }

        $dados = $response->json('dados') ?? [];
        $status = $dados['statusProposicao'] ?? [];

        return [
            'situacao' => $status['descricaoSituacao'] ?? null,
            'orgao' => $status['siglaOrgao'] ?? null,
        ];
    }

    public function getTramitations(string $billId): array
    {
        $all = [];
        $page = 1;

        do {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$this->baseUrl}/proposicoes/{$billId}/tramitacoes");

            if ($response->failed()) {
                throw new \RuntimeException("Failed to fetch tramitations for bill {$billId}: " . $response->status());
            }

            $data = $response->json('dados') ?? [];
            $all = array_merge($all, $data);
            $page++;
        } while (count($data) === 100);

        return collect($all)->map(fn ($t) => [
            'event_date' => isset($t['dataHora']) ? substr($t['dataHora'], 0, 10) : null,
            'sequence' => $t['sequencia'] ?? null,
            'committee_code' => $t['siglaOrgao'] ?? null,
            'action_description' => $t['descricaoTramitacao'] ?? null,
            'status_description' => $t['descricaoSituacao'] ?? null,
            'status_code' => $t['codSituacao'] ?? null,
            'dispatch_text' => $t['despacho'] ?? null,
        ])->all();
    }

    public function getStatusAndHistory(string $billId): array
    {
        return [
            'status' => $this->getBillStatus($billId),
            'tramitations' => $this->getTramitations($billId),
        ];
    }
    
    public function getProfessions(string $id): array
    {
        $response = Http::withOptions(['verify' => false])->get("{$this->baseUrl}/deputados/{$id}/profissoes");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch professions for legislator {$id}: " . $response->status());
        }

        $professions = $response->json('dados') ?? [];

        return collect($professions)
            ->filter(fn ($p) => !empty($p['titulo']))
            ->map(fn ($p) => [
                'original_name' => $p['titulo'],
                'normalized_name' => $this->normalizeProfessionName($p['titulo']),
                'source' => 'lower_house',
                'is_primary' => null,
                'registered_at' => isset($p['dataHora']) ? substr($p['dataHora'], 0, 10) : null,
                'camara_code' => $p['codTipoProfissao'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function getTopics(string $billId): array
    {
        $response = Http::withOptions(['verify' => false])
            ->get("{$this->baseUrl}/proposicoes/{$billId}/temas");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch topics for bill {$billId}: " . $response->status());
        }

        $temas = $response->json('dados') ?? [];

        return collect($temas)
            ->filter(fn ($t) => !empty($t['tema']))
            ->map(fn ($t) => [
                'external_id' => isset($t['codTema']) ? (string) $t['codTema'] : null,
                'name' => trim($t['tema']),
                'relevance' => isset($t['relevancia']) ? (int) $t['relevancia'] : null,
            ])
            ->values()
            ->all();
    }
    
}