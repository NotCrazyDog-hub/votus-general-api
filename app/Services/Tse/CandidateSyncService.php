<?php

namespace App\Services\Tse;

use App\Enums\CandidateOffice;
use App\Models\CandidacyHistory;
use App\Models\Candidate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sincroniza candidatos a partir da API DivulgaCandContas do TSE, no lugar dos
 * CSVs de Dados Abertos.
 *
 * O fluxo é o da especificação: listar (só pra descobrir IDs) -> detalhe ->
 * mapear -> persistir -> foto/documento -> histórico -> vices.
 *
 * Tudo é idempotente: rodar de novo atualiza o candidato existente em vez de
 * duplicar. A identidade é `external_id` (SQ_CANDIDATO) + ano da eleição —
 * não por `round`, que não existe mais.
 */
class CandidateSyncService
{
    public function __construct(private readonly DivulgaCandContasService $api) {}

    /**
     * Sincroniza todos os candidatos de um cargo numa UF.
     *
     * @return array{persisted: int, histories: int, photos: int, documents: int, running_mates: int, failed: array<int, string>}
     */
    public function syncOffice(
        int $year,
        string $uf,
        string $electionId,
        CandidateOffice $office,
        string $disk = 'supabase',
        bool $withFiles = true,
        ?callable $onProgress = null,
    ): array {
        $summary = [
            'persisted' => 0,
            'histories' => 0,
            'photos' => 0,
            'documents' => 0,
            'running_mates' => 0,
            'failed' => [],
        ];

        $listed = $this->api->listCandidates($year, $uf, $electionId, DivulgaCandContasService::CARGO_CODES[$office->value]);

        foreach ($listed as $row) {
            $externalId = $row['id'] ?? null;

            // A listagem é só descoberta; sem ID não há detalhe possível.
            if ($externalId === null || $externalId === '') {
                continue;
            }

            try {
                $detail = $this->api->getCandidate($year, $uf, $electionId, $externalId);

                $candidate = $this->persistCandidate($detail, $year, $office);

                $summary['persisted']++;
                $summary['histories'] += $this->syncHistory($candidate, $detail, $year);

                if ($withFiles) {
                    $summary['photos'] += $this->syncPhoto($candidate, $detail, $disk);
                    $summary['documents'] += $this->syncProposalDocument($candidate, $detail, $disk);
                }

                if ($onProgress !== null) {
                    $onProgress($candidate);
                }
            } catch (\Throwable $e) {
                $summary['failed'][] = "{$externalId}: {$e->getMessage()}";
            }
        }

        // Segundo passo, depois de todos os candidatos gravados: o vice pode
        // não existir no banco quando o titular é processado. Assim o vínculo
        // funciona em qualquer ordem e reexecutar o sync recria o que faltou.
        $summary['running_mates'] = $this->linkRunningMatesFor($year, $uf);

        return $summary;
    }

    /**
     * Mapeia o detalhe do candidato e grava com updateOrCreate.
     */
    public function persistCandidate(array $detail, int $year, CandidateOffice $office): Candidate
    {
        $externalId = $detail['id'] ?? null;

        if ($externalId === null || $externalId === '') {
            throw new RuntimeException('Detalhe do candidato sem id.');
        }

        $party = is_array($detail['partido'] ?? null) ? $detail['partido'] : [];
        $cargo = is_array($detail['cargo'] ?? null) ? $detail['cargo'] : [];

        return Candidate::updateOrCreate(
            ['external_id' => (int) $externalId, 'election_year' => $year],
            [
                'ballot_number' => $this->stringOrNull($detail['numero'] ?? null),
                'coverage_scope' => $this->upperOrNull($detail['localCandidatura'] ?? null),
                'state' => $this->stringOrNull($detail['ufCandidatura'] ?? null),
                'office_code' => isset($cargo['codigo']) ? (int) $cargo['codigo'] : null,
                'office_name' => $this->resolveOfficeName($cargo['nome'] ?? null, $office),
                'civil_name' => $this->stringOrNull($detail['nomeCompleto'] ?? null),
                'ballot_name' => $this->stringOrNull($detail['nomeUrna'] ?? null),
                'cpf' => $this->digitsOrNull($detail['cpf'] ?? null),
                'party_acronym' => $this->upperOrNull($party['sigla'] ?? null),
                'party_name' => $this->stringOrNull($party['nome'] ?? null),
                'education_level' => $this->stringOrNull($detail['grauInstrucao'] ?? null),
                'occupation' => $this->stringOrNull($detail['ocupacao'] ?? null),
                'race_color' => $this->stringOrNull($detail['descricaoCorRaca'] ?? null),
                'election_year' => $year,
                'judgment_status' => $this->normalizeJudgmentStatus($detail['descricaoSituacao'] ?? null),
                // O TSE entrega muito campo sem coluna correspondente
                // (gasto por turno, nacionalidade, محل candidatura, vícios...).
                // Guardar o JSON preserva essa informação sem criar coluna
                // nova para cada propriedade.
                'raw_data' => $detail,
                // Candidato titular nunca é vice de ninguém.
                'running_mate_of_id' => null,
            ],
        );
    }

    /**
     * Grava `eleicoesAnteriores` em candidacy_histories.
     *
     * Só entra o que é ANTERIOR ao ano sincronizado: a candidatura corrente
     * já está em `candidates`, e duplicá-la no histórico mostraria a mesma
     * candidatura duas vezes na tela.
     *
     * `round` fica null de propósito — a API não informa turno de eleição
     * anterior, e a etapa de resultados/segundo turno vai preencher depois.
     */
    public function syncHistory(Candidate $candidate, array $detail, int $currentYear): int
    {
        $previous = $detail['eleicoesAnteriores'] ?? [];

        if (! is_array($previous)) {
            return 0;
        }

        $saved = 0;

        foreach ($previous as $item) {
            if (! is_array($item)) {
                continue;
            }

            $year = (int) ($item['nrAno'] ?? 0);
            $externalId = $item['id'] ?? null;

            if ($year <= 0 || $externalId === null || $externalId === '') {
                continue;
            }

            // A candidatura do ano sincronizado não vira histórico: ela já
            // está em `candidates`.
            if ($year >= $currentYear) {
                continue;
            }

            CandidacyHistory::updateOrCreate(
                [
                    'candidate_id' => $candidate->id,
                    'candidacy_external_id' => (int) $externalId,
                ],
                [
                    'election_year' => $year,
                    'round' => null,
                    'state' => $this->stringOrNull($item['sgUe'] ?? null),
                    'office_name' => $this->stringOrNull($item['cargo'] ?? null),
                    'ballot_number' => $this->stringOrNull($item['nrCandidato'] ?? null),
                    // O histórico da API traz só a sigla do partido.
                    'party_acronym' => $this->upperOrNull($item['partido'] ?? null),
                    'party_name' => null,
                    'candidacy_status' => null,
                    'result_status' => $this->stringOrNull($item['situacaoTotalizacao'] ?? null),
                    'raw_data' => $item,
                ],
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * Baixa a foto (fotoUrl) e guarda o CAMINHO em photo_path.
     *
     * A URL do TSE nunca é salva em photo_path: esse campo é caminho de
     * arquivo, e a URL é o endereço da origem, não o do arquivo que servimos.
     */
    public function syncPhoto(Candidate $candidate, array $detail, string $disk = 'supabase'): int
    {
        // fotoUrlPublicavel é a variante liberada para divulgação; cai para a
        // outra só quando ela não vier.
        $url = $detail['fotoUrlPublicavel'] ?? $detail['fotoUrl'] ?? null;

        $contents = $this->api->downloadPhoto($this->stringOrNull($url));

        if ($contents === null) {
            return 0;
        }

        $path = "candidates/photos/{$candidate->external_id}.jpg";

        try {
            Storage::disk($disk)->put($path, $contents);
        } catch (\Throwable $e) {
            Log::warning("CandidateSyncService: falha ao gravar a foto do candidato {$candidate->external_id}: {$e->getMessage()}");

            return 0;
        }

        $candidate->update(['photo_path' => $path]);

        return 1;
    }

    /**
     * Localiza o plano de governo entre `arquivos` (codTipo = 5).
     */
    public function findProposalDocument(array $detail): int|string|null
    {
        $files = $detail['arquivos'] ?? [];

        if (! is_array($files)) {
            return null;
        }

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            if ((string) ($file['codTipo'] ?? '') !== DivulgaCandContasService::ARQUIVO_PLANO_DE_GOVERNO) {
                continue;
            }

            $id = $file['idArquivo'] ?? null;

            if ($id !== null && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * Baixa o plano de governo (arquivos com codTipo = 5).
     *
     * Falha aqui não impede o candidato de ser salvo — o registro já foi
     * persistido antes, e um documento indisponível não deve custar o
     * candidato inteiro.
     */
    public function syncProposalDocument(Candidate $candidate, array $detail, string $disk = 'supabase'): int
    {
        $file = $this->findProposalDocument($detail);

        if ($file === null) {
            return 0;
        }

        $contents = $this->api->downloadDocument($file);

        if ($contents === null) {
            Log::warning("CandidateSyncService: não foi possível baixar o plano de governo do candidato {$candidate->external_id} (idArquivo {$file}).");

            return 0;
        }

        $path = "candidates/proposals/{$candidate->external_id}.pdf";

        try {
            Storage::disk($disk)->put($path, $contents);
        } catch (\Throwable $e) {
            Log::warning("CandidateSyncService: falha ao gravar o plano de governo do candidato {$candidate->external_id}: {$e->getMessage()}");

            return 0;
        }

        $candidate->update(['proposal_document_path' => $path]);

        return 1;
    }

    /**
     * Liga vices/suplentes ao titular usando `vices` do detalhe do TSE.
     *
     * Reaproveita a relação já existente (`running_mate_of_id`) — nenhuma
     * tabela ou arquitetura nova. O vice é sempre identificado por
     * `vices[].sq_CANDIDATO` (o SQ_CANDIDATO do vice na mesma eleição), que
     * só existe se o vice já tiver sido sincronizado antes; por isso o comando
     * roda este passo de novo depois de persistir todos os candidatos.
     */
    public function linkRunningMatesFor(int $year, ?string $uf = null): int
    {
        $linked = 0;

        $candidates = Candidate::where('election_year', $year)
            ->whereNull('running_mate_of_id')
            ->when($uf, fn ($q) => $q->where('state', $uf))
            ->get(['id', 'external_id', 'raw_data']);

        foreach ($candidates as $candidate) {
            $vices = $candidate->raw_data['vices'] ?? null;

            // Só os cargos que têm vice/suplente trazem `vices` preenchido;
            // os demais vêm null/vazio e são ignorados naturalmente.
            if (! is_array($vices) || $vices === []) {
                continue;
            }

            foreach ($vices as $vice) {
                if (! is_array($vice)) {
                    continue;
                }

                $mateExternalId = $vice['sq_CANDIDATO'] ?? null;

                if ($mateExternalId === null || $mateExternalId === '') {
                    continue;
                }

                // O vice precisa existir como candidato da MESMA eleição: sem
                // isso, um SQ_CANDIDATO de eleição antiga viraria vínculo errado.
                $mate = Candidate::where('external_id', (int) $mateExternalId)
                    ->where('election_year', $year)
                    ->whereNull('running_mate_of_id')
                    ->first();

                if ($mate === null || $mate->id === $candidate->id) {
                    continue;
                }

                $mate->update(['running_mate_of_id' => $candidate->id]);
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * `descricaoSituacao` vem em texto ("Deferido", "Indeferido"). O projeto
     * compara com os valores em caixa alta nos scopes approved()/rejected(),
     * então a normalização tem que produzir exatamente "DEFERIDO"/"INDEFERIDO".
     *
     * Usa-se `codigoSituacaoCandidato` NÃO como código de judgment_status_code:
     * aquela coluna foi removida, e o texto já é o que o resto do projeto usa.
     */
    private function normalizeJudgmentStatus(?string $situacao): ?string
    {
        $value = $this->stringOrNull($situacao);

        if ($value === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim($value));

        // Acronyms não existem em português; remove só acentos.
        $normalized = strtr($normalized, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'Ê' => 'E', 'È' => 'E',
            'Í' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ]);

        return match (true) {
            str_starts_with($normalized, 'INDEFERIDO') => 'INDEFERIDO',
            str_starts_with($normalized, 'DEFERIDO') => 'DEFERIDO',
            default => $normalized,
        };
    }

    /**
     * `office_name` precisa bater com CandidateOffice::toTseDescription()
     * ('GOVERNADOR', 'DEPUTADO ESTADUAL'...), que é o valor já gravado e o que o
     * CandidateService usa para filtrar as listagens públicas. A API devolve
     * 'Governador'/'Deputado Estadual' — sem converter, as listagens voltariam
     * vazias.
     */
    private function resolveOfficeName(?string $apiName, CandidateOffice $office): string
    {
        $value = $this->stringOrNull($apiName);

        if ($value === null) {
            return $office->toTseDescription();
        }

        $normalized = mb_strtoupper(trim($value));

        return CandidateOffice::fromTseDescription($normalized)?->toTseDescription() ?? $normalized;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function upperOrNull(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        return $value === null ? null : mb_strtoupper($value);
    }

    /**
     * CPF é crescente; a API às vezes devolve com máscara.
     */
    private function digitsOrNull(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return ($digits === '' || $digits === null) ? null : $digits;
    }
}
