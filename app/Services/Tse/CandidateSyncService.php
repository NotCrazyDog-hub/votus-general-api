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

        // Vices já tratados nesta execução (external_id => true) e pendências
        // coletadas dos detalhes dos titulares — processadas na fase 2.
        $processed = [];
        $pendingVices = [];

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
                $processed[(string) $externalId] = true;

                // O detalhe do titular traz os vices (`vices[].sq_CANDIDATO`),
                // mas o vice NÃO aparece na listagem do cargo: ele precisa de
                // uma consulta própria ao detalhe. Coleta aqui e processa na
                // segunda fase, depois que todos os titulares estão gravados.
                foreach ($this->viceExternalIds($detail) as $viceExternalId) {
                    $pendingVices[] = ['id' => $viceExternalId, 'titular' => $candidate];
                }

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

        // Segunda fase: cada vice encontrado nos detalhes é consultado pelo SEU
        // SQ_CANDIDATO e persistido como registro próprio, já ligado ao titular.
        // `processed` garante que um vice não seja tratado duas vezes na mesma
        // execução (ex.: vice que também apareceu na listagem).
        $vices = $this->syncPendingVices(
            $pendingVices,
            $processed,
            $year,
            $uf,
            $electionId,
            $office,
            $disk,
            $withFiles,
            $onProgress,
        );

        foreach (['persisted', 'histories', 'photos', 'documents', 'running_mates'] as $key) {
            $summary[$key] += $vices[$key];
        }

        $summary['failed'] = [...$summary['failed'], ...$vices['failed']];

        // Terceiro passo, depois de todos os candidatos gravados: religa vices
        // que ficaram órfãos (ex.: os vindos da listagem, gravados com null).
        // O vínculo criado na fase 2 já está valendo e não é recontado — esta
        // fase só pega quem ainda está com `running_mate_of_id` null.
        $summary['running_mates'] += $this->linkRunningMatesFor($year, $uf);

        return $summary;
    }

    /**
     * Mapeia o detalhe do candidato e grava com updateOrCreate.
     *
     * `$runningMateOfId` só é preenchido quando o registro é um vice e o
     * vínculo com o titular é conhecido no momento da gravação: assim a
     * atualização nunca zera um relacionamento válido com `null` durante uma
     * nova sincronização. Titulares (e vices vindos da listagem, religados no
     * passo final) usam o padrão `null`.
     */
    public function persistCandidate(array $detail, int $year, CandidateOffice $office, ?int $runningMateOfId = null): Candidate
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
                // Titular é null; o vice recebe o id interno do titular aqui
                // mesmo, sem passar por um update posterior que pudesse deixar
                // o vínculo zerado no meio da sincronização.
                'running_mate_of_id' => $runningMateOfId,
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

            if ($year >= $currentYear) {
                continue;
            }

            CandidacyHistory::updateOrCreate(
                [
                    'candidacy_external_id' => (int) $externalId,
                    'election_year' => (int) ($item['nrAno'] ?? $year),
                    'round' => (int) ($item['nrTurno'] ?? 1),
                ],
                [
                    'candidate_id' => $candidate->id,
                    'state' => $candidate->state,
                    'office_name' => $this->stringOrNull($item['cargo'] ?? null),
                    'ballot_number' => $this->stringOrNull($item['nrCandidato'] ?? null),
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
     * Extrai os SQ_CANDIDATO dos vices listados no detalhe do titular.
     *
     * O campo vem como array (ou null/ausente quando o cargo não tem vice).
     * Só o identificador interessa aqui: o restante do payload do vice é
     * obtido da consulta de detalhe, que é a fonte completa.
     *
     * @return array<int, int|string>
     */
    private function viceExternalIds(array $detail): array
    {
        $vices = $detail['vices'] ?? null;

        if (! is_array($vices)) {
            return [];
        }

        $ids = [];

        foreach ($vices as $vice) {
            if (! is_array($vice)) {
                continue;
            }

            $id = $vice['sq_CANDIDATO'] ?? null;

            if ($id === null || $id === '') {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Segunda fase do sync: registra os vices como candidatos próprios.
     *
     * Para cada pendência coletada na fase 1:
     *
     *  1. pula se o vice já foi tratado nesta execução (apareceu na listagem,
     *     ou outro titular apontou o mesmo SQ) — evita consulta e download
     *     repetidos;
     *  2. consulta o detalhe pelo SQ_CANDIDATO do vice, pelo mesmo caminho de
     *     rede dos demais candidatos (`DivulgaCandContasService::getCandidate`);
     *  3. persiste com a MESMA lógica de cadastro/atualização (updateOrCreate
     *     por external_id + ano, raw_data preservado), já apontando
     *     `running_mate_of_id` para o id interno do titular;
     *  4. histórico, foto e plano de governo seguem a rotina usual — falha de
     *     download nunca derruba o cadastro.
     *
     * Falha na consulta do detalhe entra em `failed` e o sync segue: um vice
     * inacessível não pode impedir o resto da UF.
     *
     * @param  array<int, array{id: int|string, titular: Candidate}>  $pending
     * @param  array<string, true>  $processed
     * @return array{persisted: int, histories: int, photos: int, documents: int, running_mates: int, failed: array<int, string>}
     */
    private function syncPendingVices(
        array $pending,
        array $processed,
        int $year,
        string $uf,
        string $electionId,
        CandidateOffice $office,
        string $disk,
        bool $withFiles,
        ?callable $onProgress,
    ): array {
        $summary = [
            'persisted' => 0,
            'histories' => 0,
            'photos' => 0,
            'documents' => 0,
            'running_mates' => 0,
            'failed' => [],
        ];

        foreach ($pending as $item) {
            $viceExternalId = (string) $item['id'];
            $titular = $item['titular'];

            // Dedup: já persistido na fase 1, ou já tratado aqui.
            if (isset($processed[$viceExternalId])) {
                continue;
            }

            // Dado degenerado (titular listado como próprio vice): gravar
            // sobrescreveria o titular com o vínculo apontando para si.
            if ($viceExternalId === (string) $titular->external_id) {
                continue;
            }

            $processed[$viceExternalId] = true;

            try {
                $detail = $this->api->getCandidate($year, $uf, $electionId, $item['id']);

                // O vínculo vai na MESMA gravação do updateOrCreate: nunca há
                // um instante com o vice registrado porém isolado, e um
                // vínculo anterior inválido é corrigido aqui mesmo.
                $vice = $this->persistCandidate($detail, $year, $office, $titular->id);

                $summary['persisted']++;
                $summary['histories'] += $this->syncHistory($vice, $detail, $year);

                if ($withFiles) {
                    $summary['photos'] += $this->syncPhoto($vice, $detail, $disk);
                    $summary['documents'] += $this->syncProposalDocument($vice, $detail, $disk);
                }

                $summary['running_mates']++;

                if ($onProgress !== null) {
                    $onProgress($vice);
                }
            } catch (\Throwable $e) {
                $summary['failed'][] = "{$viceExternalId}: {$e->getMessage()}";
            }
        }

        return $summary;
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
