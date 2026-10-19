<?php

namespace App\Services\Tse;

use App\Enums\CandidateOffice;
use App\Models\CandidacyHistory;
use App\Models\Candidate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa RESULTADOS eleitorais dos JSONs oficiais de resultados.tse.jus.br
 * para os registros JÁ EXISTENTES de `candidacy_histories`.
 *
 * Fronteiras importantes:
 *
 *  - esta classe é a fonte de RESULTADOS (apuração); a fonte CADASTRAL
 *    continua sendo a API DivulgaCandContas via `CandidateSyncService`, que
 *    não é tocado aqui;
 *  - os arquivos são estáticos e consultados por `Http` — sem Playwright;
 *  - os códigos de eleição (EA11: `config/ele-c.json`) vêm da configuração
 *    oficial, nunca hardcoded: cada eleição/turno tem `cd` próprio (2026:
 *    6257 federal/1ºT, 6258 federal/2ºT, 6259 estadual/1ºT, 6260 estadual/2ºT);
 *  - o arquivo EA20 (`-u.json`) cobre todos os cargos e turnos e traz a
 *    situação oficial (`st`) de cada candidatura — é a única fonte usada;
 *  - correspondência PRIMEIRO no `Candidate` do ano corrente
 *    (`external_id` = `sqcand`, com validação de cargo/UF). O histórico do
 *    turno pedido NASCE desse candidato quando ainda não existe — a relação
 *    usa `Candidate.id` como `candidate_id`. Candidatura sem candidato vira
 *    estatística/log — NUNCA um histórico ou candidato inventado.
 *
 * Identidade no banco: a unique vigente é
 * `(election_year, candidacy_external_id, round)` (migration
 * 2026_10_13_152814), então cada turno vive na sua linha — o registro do 1º
 * turno nunca é reescrito para virar 2º turno.
 */
class TseElectionResultsService
{
    /**
     * Situações oficiais conhecidas (DS_SIT_TOT_TURNO / campo `st`) no
     * formato canônico gravado em `result_status`.
     *
     * Mapeamento EXPLÍCITO, nunca por substring: "Não eleito" contém
     * "eleito" e classificaria eleito quem não foi. Chaves em caixa baixa,
     * com e sem acento, cobrem as grafias oficiais; qualquer outro valor é
     * preservado literalmente e contabilizado como desconhecido.
     *
     * @var array<string, string>
     */
    private const KNOWN_STATUSES = [
        'eleito' => 'Eleito',
        'eleita' => 'Eleita',
        'eleito por qp' => 'Eleito por QP',
        'eleito por media' => 'Eleito por média',
        'eleito por média' => 'Eleito por média',
        'nao eleito' => 'Não eleito',
        'não eleito' => 'Não eleito',
        'suplente' => 'Suplente',
        '2º turno' => '2º turno',
        '2o turno' => '2º turno',
        'segundo turno' => '2º turno',
    ];

    /**
     * Cache curto do EA11 (payload + ETag/Last-Modified).
     */
    private const CONFIG_CACHE_KEY = 'tse:resultados:ele-c';

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return config('services.tse.resultados', []);
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config()['base_url'] ?? 'https://resultados.tse.jus.br'), '/');
    }

    /**
     * Lê a configuração oficial EA11 (`ele-c.json`) com cache condicional.
     *
     * `ETag`/`Last-Modified` são enviados de volta quando o servidor os
     * forneceu (304 reaproveita o corpo em cache); o TTL é curto para não
     * servir resultado velho durante a apuração.
     *
     * @return array<string, mixed>
     *
     * @throws TseResultsException
     */
    public function fetchElectionConfig(): array
    {
        $url = $this->baseUrl().(string) ($this->config()['config_path'] ?? '/oficial/comum/config/ele-c.json');
        $cached = Cache::get(self::CONFIG_CACHE_KEY);

        $headers = [];
        if (is_array($cached)) {
            if (! empty($cached['etag'])) {
                $headers['If-None-Match'] = $cached['etag'];
            }
            if (! empty($cached['last_modified'])) {
                $headers['If-Modified-Since'] = $cached['last_modified'];
            }
        }

        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->timeout((int) ($this->config()['timeout'] ?? 60))
                ->retry(
                    (int) ($this->config()['retries'] ?? 3),
                    (int) ($this->config()['retry_delay'] ?? 1000),
                    fn ($e) => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->status() >= 500)
                        || ($e instanceof RequestException && $e->response->status() === 429),
                    throw: false,
                )
                ->get($url);
        } catch (ConnectionException $e) {
            throw new TseResultsException('timeout', "Timeout ao consultar {$url}: {$e->getMessage()}");
        } catch (Throwable $e) {
            throw new TseResultsException('config', "Falha ao consultar {$url}: {$e->getMessage()}");
        }

        if ($response->status() === 304 && is_array($cached) && isset($cached['body'])) {
            return $this->assertConfig($cached['body'], $url);
        }

        $this->assertHttpSuccess($response, $url);

        $json = $this->decodeJson((string) $response->body(), $url);
        $this->assertConfig($json, $url);

        Cache::put(self::CONFIG_CACHE_KEY, [
            'etag' => $response->header('etag'),
            'last_modified' => $response->header('last-modified'),
            'body' => $json,
        ], (int) ($this->config()['cache_ttl'] ?? 120));

        return $json;
    }

    /**
     * Monta as URLs dos arquivos EA20 válidos para (ano, turno, UF, cargo)
     * a partir do EA11 — sem código de eleição fixo.
     *
     * Cargos estaduais (3/5/6/7/8) só existem por UF (`dados/{uf}/`); sem
     * `--state`, só o escopo nacional (`br`) é resolvível — e uma nota
     * explica o resto. Turno 2 só existe onde a configuração tem a UF na
     * abrangência (em 2026: AM/RN/DF/ES/AC para Governador).
     *
     * @return array{urls: array<int, string>, notes: array<int, string>}
     *
     * @throws TseResultsException
     */
    public function resolveFileUrls(int $year, int $round, ?string $state, ?CandidateOffice $office): array
    {
        $config = $this->fetchElectionConfig();
        $ciclo = 'ele'.$year;

        $entries = [];
        foreach ((array) ($config['pl'] ?? []) as $pl) {
            if (($pl['c'] ?? null) !== $ciclo) {
                continue;
            }
            foreach ((array) ($pl['e'] ?? []) as $entry) {
                $entries[] = $entry;
            }
        }

        $urls = [];
        $notes = [];
        $stateNoted = false;

        foreach ($entries as $entry) {
            if ((string) ($entry['t'] ?? '') !== (string) $round) {
                continue;
            }

            $entryCd = (int) ($entry['cd'] ?? 0);

            foreach ((array) ($entry['abr'] ?? []) as $abr) {
                $cargoCodes = array_column((array) ($abr['cp'] ?? []), 'cd');
                $scope = strtolower((string) ($abr['cd'] ?? ''));

                $wanted = $office !== null
                    ? [(string) (DivulgaCandContasService::CARGO_CODES[$office->value] ?? '')]
                    : $cargoCodes;

                foreach ($wanted as $cargoCode) {
                    if ($cargoCode === '' || ! in_array($cargoCode, $cargoCodes, true)) {
                        continue;
                    }

                    $segment = $this->scopeSegment($cargoCode, $scope, $state);

                    if ($segment === null) {
                        if ($state === null && ! $stateNoted) {
                            $notes[] = 'Cargos estaduais (governador, senador, deputados) têm arquivo por UF: informe --state (ex.: --state=CE).';
                            $stateNoted = true;
                        }

                        continue;
                    }

                    $urls[] = sprintf(
                        '%s/oficial/%s/%d/dados/%s/%s-c%04d-e%06d-u.json',
                        $this->baseUrl(),
                        $ciclo,
                        $entryCd,
                        $segment,
                        $segment,
                        (int) $cargoCode,
                        $entryCd,
                    );
                }
            }
        }

        $urls = array_values(array_unique($urls));

        if ($urls === []) {
            $notes[] = $state !== null
                ? sprintf('A configuração oficial não prevê turno %d para %s%s em %d (sem arquivo publicado).', $round, $state, $office ? '/'.strtoupper($office->value) : '', $year)
                : sprintf('Nenhum arquivo aplicável para turno %d em %d.', $round, $year);
        }

        return ['urls' => $urls, 'notes' => $notes];
    }

    /**
     * Segmento de diretório do arquivo (`br` ou UF), ou null quando não há
     * arquivo para esse recorte.
     *
     * Presidente (cargo 1) é sempre nacional; cargos estaduais exigem UF —
     * no turno 1 a abrangência vem marcada como `br` mas o arquivo é por UF
     * (validado em produção: /dados/ce/ce-c0003-e006259-u.json), e no turno 2
     * só as UFs listadas na abrangência têm eleição.
     */
    private function scopeSegment(string $cargoCode, string $scope, ?string $state): ?string
    {
        if ($cargoCode === (string) DivulgaCandContasService::CARGO_CODES[CandidateOffice::President->value]) {
            return 'br';
        }

        if ($state === null) {
            return null;
        }

        $uf = strtolower($state);

        return ($scope === 'br' || $scope === $uf) ? $uf : null;
    }

    /**
     * Baixa um arquivo EA20 e valida o CONTEÚDO (HTTP 200 não basta).
     *
     * @return array{json: array<string, mixed>, meta: array<string, mixed>}
     *
     * @throws TseResultsException
     */
    public function fetchResultFile(string $url): array
    {
        $config = $this->config();

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->timeout((int) ($config['timeout'] ?? 60))
                ->retry(
                    (int) ($config['retries'] ?? 3),
                    (int) ($config['retry_delay'] ?? 1000),
                    fn ($e) => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->status() >= 500)
                        || ($e instanceof RequestException && $e->response->status() === 429),
                    throw: false,
                )
                ->get($url);
        } catch (ConnectionException $e) {
            throw new TseResultsException('timeout', "Timeout ao baixar {$url}: {$e->getMessage()}");
        }

        $this->assertHttpSuccess($response, $url);

        $body = (string) $response->body();

        // 200 com página de erro do proxy/portal é o engano mais comum: o
        // servidor devolve HTML com status ok em manutenção.
        $looksHtml = str_contains((string) $response->header('content-type'), 'text/html')
            || str_starts_with(ltrim($body), '<');

        if ($looksHtml) {
            throw new TseResultsException('html', "Conteúdo HTML inesperado em {$url} (esperado JSON de resultados).");
        }

        $json = $this->decodeJson($body, $url);

        if (! isset($json['carg']) || ! is_array($json['carg'])) {
            throw new TseResultsException('structure', "JSON de {$url} sem a estrutura esperada (chave `carg`).");
        }

        return [
            'json' => $json,
            'meta' => [
                'url' => $url,
                'ele' => isset($json['ele']) ? (string) $json['ele'] : null,
                't' => isset($json['t']) ? (string) $json['t'] : null,
                'tpabr' => isset($json['tpabr']) ? (string) $json['tpabr'] : null,
                'cdabr' => isset($json['cdabr']) ? (string) $json['cdabr'] : null,
                'dg' => isset($json['dg']) ? (string) $json['dg'] : null,
                'hg' => isset($json['hg']) ? (string) $json['hg'] : null,
                'tf' => isset($json['tf']) ? (string) $json['tf'] : null,
                'and' => isset($json['and']) ? (string) $json['and'] : null,
            ],
        ];
    }

    /**
     * Localiza as candidaturas em QUALQUER profundidade do JSON.
     *
     * Percorre o documento carregando o `cd` do cargo mais próximo e coleta
     * todo array chamado `cand` (no formato real: carg > agr > par > cand).
     * Um mesmo `sqcand` repetido em estruturas distintas é contado como
     * duplicado e processado UMA vez só.
     *
     * @param  array<string, mixed>  $json
     * @return array{candidates: array<int, array<string, mixed>>, duplicates: int}
     */
    public function extractCandidates(array $json): array
    {
        $candidates = [];
        $duplicates = 0;

        $walk = function (mixed $node, ?string $cargoCd) use (&$walk, &$candidates, &$duplicates): void {
            if (! is_array($node)) {
                return;
            }

            foreach ($node as $key => $value) {
                if ($key === 'carg' && is_array($value)) {
                    foreach ($value as $cargo) {
                        $nextCd = is_array($cargo) && isset($cargo['cd']) ? (string) $cargo['cd'] : $cargoCd;
                        $this->collectNode($cargo, $nextCd, $walk, $candidates, $duplicates);

                        if (is_array($cargo) && isset($cargo['cd'])) {
                            // já percorrido com o cargo correto acima; evita
                            // reprocessar o mesmo nó pelo caminho genérico.
                            continue;
                        }
                    }

                    continue;
                }

                if ($key === 'cand' && is_array($value)) {
                    foreach ($value as $cand) {
                        if (! is_array($cand)) {
                            continue;
                        }

                        $sqcand = isset($cand['sqcand']) ? (string) $cand['sqcand'] : '';

                        if ($sqcand === '') {
                            continue;
                        }

                        if (isset($candidates[$sqcand])) {
                            $duplicates++;

                            continue;
                        }

                        $candidates[$sqcand] = [
                            'sqcand' => $sqcand,
                            'st' => isset($cand['st']) && is_string($cand['st']) ? $cand['st'] : null,
                            'cargo_cd' => $cargoCd,
                            'cand' => $cand,
                        ];
                    }

                    continue;
                }

                $this->collectNode($value, $cargoCd, $walk, $candidates, $duplicates);
            }
        };

        $walk($json, null);

        return [
            'candidates' => array_values($candidates),
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<string, array<string, mixed>>  $candidates
     */
    private function collectNode(mixed $node, ?string $cargoCd, callable $walk, array &$candidates, int &$duplicates): void
    {
        if (is_array($node)) {
            $walk($node, $cargoCd);
        }
    }

    /**
     * Texto canônico da situação oficial; valores desconhecidos voltam
     * EXATAMENTE como vieram do TSE (nada é inventado ou truncado).
     */
    public function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $trimmed = trim($status);

        if ($trimmed === '') {
            return null;
        }

        $key = mb_strtolower($trimmed);

        return self::KNOWN_STATUSES[$key] ?? $trimmed;
    }

    public function isKnownStatus(?string $status): bool
    {
        if ($status === null || trim($status) === '') {
            return false;
        }

        return isset(self::KNOWN_STATUSES[mb_strtolower(trim($status))]);
    }

    /**
     * Código de cargo (TSE) → cargo do projeto, pela mesma tabela usada na
     * sincronização (`DivulgaCandContasService::CARGO_CODES`). Dep. Distrital
     * (8) não tem case no enum — retorna null e a guarda de escopo pula.
     */
    public function officeFromCargoCode(?string $cargoCd): ?CandidateOffice
    {
        if ($cargoCd === null || $cargoCd === '') {
            return null;
        }

        foreach (DivulgaCandContasService::CARGO_CODES as $name => $code) {
            if ((string) $code === $cargoCd) {
                return CandidateOffice::tryFrom($name);
            }
        }

        return null;
    }

    /**
     * Guarda de escopo: evita atualizar cruzado. UF vem do arquivo
     * (`tpabr=uf`); presidente (`tpabr=br`) não tem UF na fonte e pula.
     * Cargo é comparado pelo `office_name` da linha.
     */
    private function withinScope(CandidacyHistory $row, array $meta, ?CandidateOffice $office): bool
    {
        if (($meta['tpabr'] ?? null) === 'uf' && strtoupper((string) $row->state) !== strtoupper((string) $meta['cdabr'])) {
            return false;
        }

        if ($office !== null && strtoupper((string) $row->office_name) !== $office->toTseDescription()) {
            return false;
        }

        return true;
    }

    /**
     * Guarda de escopo para o CANDIDATO-semente: mesma regra do histórico.
     * Presidente (tpabr=br) não valida UF — o nacional pula; cargo compara
     * pelo `office_name` do candidato contra a descrição TSE (o `office_name`
     * do candidato vem em MAIÚSCULO do sincronizador, por isso o strtoupper).
     */
    private function candidateWithinScope(Candidate $candidate, array $meta, ?CandidateOffice $office): bool
    {
        if (($meta['tpabr'] ?? null) === 'uf' && strtoupper((string) $candidate->state) !== strtoupper((string) $meta['cdabr'])) {
            return false;
        }

        if ($office !== null && strtoupper((string) $candidate->office_name) !== $office->toTseDescription()) {
            return false;
        }

        return true;
    }

    /**
     * Histórico novo a partir do candidato: copia os dados CADASTRAIS dele
     * (`candidate_id` = `Candidate.id`) e deixa `result_status` nulo — a
     * situação oficial entra no fluxo normal de escrita logo depois.
     * Em dry-run, devolve a instância NÃO salva e grava nada (o chamador só
     * a usa como base para a comparação; nada persiste).
     */
    private function freshHistoryFromCandidate(Candidate $candidate, int $year, int $round, bool $dryRun): ?CandidacyHistory
    {
        $row = new CandidacyHistory([
            'candidate_id' => $candidate->id,
            'candidacy_external_id' => $candidate->external_id,
            'election_year' => $year,
            'round' => $round,
            'state' => $candidate->state,
            'office_name' => $candidate->office_name,
            'ballot_number' => $candidate->ballot_number,
            'party_acronym' => $candidate->party_acronym,
            'party_name' => $candidate->party_name,
            'candidacy_status' => null,
            'result_status' => null,
            'raw_data' => $candidate->raw_data,
        ]);

        if ($dryRun) {
            return null;
        }

        $row->save();

        return $row;
    }

    /**
     * Atualiza os registros EXISTENTES de `candidacy_histories` do ano
     * escolhido a partir das candidaturas extraídas de um EA20.
     *
     * Por candidatura: acha a linha de (ano, sqcand, turno); sem linha e
     * pedido o turno 2, cria SÓ o registro do 2º turno a partir da linha do
     * 1º turno (a unique por turno permite as duas coexistirem); sem linha
     * no turno 1, vira "sem correspondência" — NUNCA cria histórico só para
     * acomodar resultado. Grava APENAS `result_status` + merge de `raw_data`
     * (`RESULTADO_TSE`/`RESULTADO_TSE_EM`): dados cadastrais intactos.
     *
     * @param  array{meta: array<string, mixed>, candidates: array<int, array<string, mixed>>}  $extracted
     * @param  array<string, int>  $stats
     * @return array<string, int>
     */
    public function applyResults(array $extracted, int $year, int $round, ?CandidateOffice $officeFilter, bool $dryRun, array $stats = []): array
    {
        $meta = $extracted['meta'];
        $stats += [
            'candidaturas' => 0,
            'atualizadas' => 0,
            'inalteradas' => 0,
            'historicos_criados' => 0,
            'sem_correspondencia' => 0,
            'escopo_incompativel' => 0,
            'sem_situacao' => 0,
            'fora_do_escopo' => 0,
            'round2_criados' => 0,
            'round2_atualizados' => 0,
            'situacoes_desconhecidas' => 0,
            'erros' => 0,
        ];

        foreach ($extracted['candidates'] as $entry) {
            $stats['candidaturas']++;
            $sqcand = $entry['sqcand'];
            $status = $entry['st'];

            if ($status === null || trim($status) === '') {
                $stats['sem_situacao']++;
                Log::warning("TseElectionResults: candidatura {$sqcand} sem situação (`st`) em ".($meta['url'] ?? '?'));

                continue;
            }

            $office = $this->officeFromCargoCode($entry['cargo_cd']);

            if ($officeFilter !== null && $office !== $officeFilter) {
                $stats['fora_do_escopo']++;

                continue;
            }

            try {
                $rows = CandidacyHistory::where('election_year', $year)
                    ->where('candidacy_external_id', $sqcand)
                    ->get();

                $row = $rows->firstWhere('round', $round)
                    ?? ($round === 1 ? $rows->firstWhere('round', null) : null);

                // O sincronizador cadastral NÃO cria histórico do ano corrente
                // (`CandidateSyncService::syncHistory` só grava eleições
                // ANTERIORES) — então a linha (ano, sqcand, turno) quase nunca
                // existe. Nesse caso, o `Candidate` do ano corrente
                // (external_id = sqcand) vira a semente do histórico: sem
                // candidato, vira "sem correspondência" e nada é criado.
                $candidate = null;
                if ($row === null) {
                    $candidate = Candidate::where('election_year', $year)
                        ->where('external_id', $sqcand)
                        ->first();

                    if ($candidate === null) {
                        $stats['sem_correspondencia']++;
                        Log::warning("TseElectionResults: candidatura {$sqcand} sem candidato em {$year} (não criada).");

                        continue;
                    }

                    if (! $this->candidateWithinScope($candidate, $meta, $office)) {
                        $stats['escopo_incompativel']++;
                        Log::warning("TseElectionResults: {$sqcand} fora do escopo do candidato ".($candidate->state.'/'.strtoupper((string) $candidate->office_name)).' vs arquivo '.($meta['cdabr'] ?? '?').'/'.($entry['cargo_cd'] ?? '?').'.');

                        continue;
                    }
                }

                $created = false;

                if ($row === null && $round === 2) {
                    // 2º turno: única criação permitida — nasce da linha do
                    // 1º turno, copiando os dados cadastrais, sem reescrever
                    // o histórico original do primeiro turno.
                    $base = $rows->firstWhere('round', 1) ?? $rows->firstWhere('round', null);

                    if ($base === null && $candidate !== null) {
                        // Sem linha-base mas COM candidato: a base é o próprio
                        // candidato (histórico do 1º turno é criado na hora a
                        // partir dele, e a linha do 2º turno nasce daquela).
                        // Dry-run: a base não persiste — conta como atualização
                        // simulada e segue, sem gravar nada.
                        $base = $this->freshHistoryFromCandidate($candidate, $year, 1, $dryRun);

                        if ($base === null) {
                            $stats['atualizadas']++;

                            continue;
                        }

                        $stats['historicos_criados']++;
                    }

                    if ($base === null) {
                        $stats['sem_correspondencia']++;
                        Log::warning("TseElectionResults: {$sqcand} sem histórico de base para o 2º turno em {$year}.");

                        continue;
                    }

                    $row = new CandidacyHistory([
                        'candidate_id' => $base->candidate_id,
                        'candidacy_external_id' => $base->candidacy_external_id,
                        'election_year' => $base->election_year,
                        'round' => 2,
                        'state' => $base->state,
                        'office_name' => $base->office_name,
                        'ballot_number' => $base->ballot_number,
                        'party_acronym' => $base->party_acronym,
                        'party_name' => $base->party_name,
                        'candidacy_status' => $base->candidacy_status,
                        'raw_data' => $base->raw_data,
                    ]);
                    $created = true;
                } elseif ($row === null) {
                    // 1º turno sem linha: o histórico NASCE do candidato
                    // (dados cadastrais copiados dele); a situação/RESULTADO_TSE
                    // entra logo abaixo, no fluxo normal de escrita.
                    $row = $this->freshHistoryFromCandidate($candidate, $year, 1, $dryRun);

                    if ($row === null) {
                        // Dry-run: conta como atualização simulada e segue —
                        // nada foi gravado, mas a análise contabiliza.
                        $stats['atualizadas']++;

                        continue;
                    }

                    $created = true;
                    $stats['historicos_criados']++;
                }

                if (! $created && ! $this->withinScope($row, $meta, $office)) {
                    $stats['escopo_incompativel']++;
                    Log::warning("TseElectionResults: {$sqcand} fora do escopo da linha ".($row->state.'/'.strtoupper((string) $row->office_name)).' vs arquivo '.($meta['cdabr'] ?? '?').'/'.($entry['cargo_cd'] ?? '?').'.');

                    continue;
                }

                $normalized = $this->normalizeStatus($status);

                if (! $this->isKnownStatus($status)) {
                    $stats['situacoes_desconhecidas']++;
                }

                $payload = [
                    'arquivo' => $meta['url'] ?? null,
                    'eleicao' => $meta['ele'] ?? null,
                    'turno' => $meta['t'] ?? null,
                    'divulgado_em' => trim(($meta['dg'] ?? '').' '.($meta['hg'] ?? '')) ?: null,
                    'totalizacao_final' => $meta['tf'] ?? null,
                    'andamento' => $meta['and'] ?? null,
                    'cargo' => $entry['cargo_cd'] ?? null,
                    // Payload COMPLETO do candidato: campos desconhecidos hoje
                    // (dvt, seq, pvapn...) ficam preservados para diagnóstico.
                    'cand' => $entry['cand'],
                ];

                $rawData = is_array($row->raw_data) ? $row->raw_data : [];
                $existing = $rawData['RESULTADO_TSE'] ?? null;

                if (! $created && $row->result_status === $normalized && $existing == $payload) {
                    $stats['inalteradas']++;

                    continue;
                }

                $rawData['RESULTADO_TSE'] = $payload;
                $rawData['RESULTADO_TSE_EM'] = now()->toIso8601String();

                if (! $dryRun) {
                    $row->result_status = $normalized;
                    $row->raw_data = $rawData;
                    $row->save();
                }

                $stats['atualizadas']++;

                if ($round === 2) {
                    $created ? $stats['round2_criados']++ : $stats['round2_atualizados']++;
                }
            } catch (Throwable $e) {
                $stats['erros']++;
                Log::warning("TseElectionResults: falha ao persistir {$sqcand}: {$e->getMessage()}");
            }
        }

        return $stats;
    }

    /**
     * HTTP não-2xx → exceção com o `kind` específico (403/404/429/5xx).
     *
     * @throws TseResultsException
     */
    private function assertHttpSuccess($response, string $url): void
    {
        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            return;
        }

        $kind = match (true) {
            $status === 403 => 'http_403',
            $status === 404 => 'http_404',
            $status === 429 => 'http_429',
            $status >= 500 => 'http_5xx',
            default => 'http',
        };

        throw new TseResultsException($kind, "TSE resultados respondeu {$status} em {$url}.");
    }

    /**
     * JSON malformado → `json`; HTML disfarçado já é detectado antes.
     *
     * @return array<string, mixed>
     *
     * @throws TseResultsException
     */
    private function decodeJson(string $body, string $url): array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new TseResultsException('json', "JSON inválido em {$url} (json_error: ".json_last_error_msg().').');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws TseResultsException
     */
    private function assertConfig(mixed $config, string $url): array
    {
        if (! is_array($config) || ! isset($config['pl']) || ! is_array($config['pl'])) {
            throw new TseResultsException('structure', "Configuração EA11 inválida em {$url} (chave `pl` ausente).");
        }

        return $config;
    }
}
