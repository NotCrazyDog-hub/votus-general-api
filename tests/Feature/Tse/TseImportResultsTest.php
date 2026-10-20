<?php

namespace Tests\Feature\Tse;

use App\Models\CandidacyHistory;
use App\Models\Candidate;
use App\Services\Tse\TseElectionResultsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Cobertura do importador de RESULTADOS (`tse:import-results`).
 *
 * Http::fake + fixtures no formato real dos EA20 validados em
 * resultados.tse.jus.br (carg > agr > par > cand; sqcand/st/e/vap/pvap/dvt;
 * cabeçalho ele/t/tpabr/cdabr) e do EA11 — nada depende do TSE real.
 *
 * O vínculo é `candidacy_external_id` (= `sqcand`) + ano/turno, com o
 * `Candidate` do ano corrente (`external_id` = `sqcand`) como semente: o
 * importador CRIA o histórico do turno a partir dele quando a linha ainda
 * não existe (o fluxo cadastral só grava eleições anteriores). Sem candidato,
 * nada é criado — vira "sem correspondência".
 */
class TseImportResultsTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    private const MAIN_SQ = 60002543969;

    private const RIVAL_SQ = 60002531351;

    private const URL_GOV_CE_R1 = 'https://resultados.tse.jus.br/oficial/ele2026/6259/dados/ce/ce-c0003-e006259-u.json';

    protected function setUp(): void
    {
        parent::setUp();

        // Sem isso, os testes de falha (500/429) esperariam 1s por retry.
        config()->set('services.tse.resultados.retry_delay', 1);
    }

    public function test_it_downloads_and_interprets_an_ea20_file(): void
    {
        $this->seedHistory();
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Arquivos lidos: 1')
            ->expectsOutputToContain('Candidaturas encontradas: 2')
            ->assertExitCode(0);

        $this->assertSame('Eleito', $this->history(self::MAIN_SQ)->result_status);
    }

    public function test_it_finds_candidates_in_nested_structures(): void
    {
        // candidatos vivem em carg > agr > par > cand — profundidade real.
        $extracted = app(TseElectionResultsService::class)->extractCandidates($this->ea20());

        $this->assertCount(2, $extracted['candidates']);
        $this->assertSame(0, $extracted['duplicates']);

        $sqs = array_column($extracted['candidates'], 'sqcand');
        $this->assertContains((string) self::MAIN_SQ, $sqs);
        $this->assertContains((string) self::RIVAL_SQ, $sqs);
    }

    public function test_it_maps_sqcand_to_candidacy_external_id(): void
    {
        $this->seedHistory();
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        // O sqcand '60002543969' do JSON virou a LINHA de candidacy_external_id
        // correspondente — nada de casar por nome ou número de urna.
        $history = CandidacyHistory::where('election_year', 2026)
            ->where('candidacy_external_id', self::MAIN_SQ)
            ->firstOrFail();

        $this->assertSame('Eleito', $history->result_status);
        $this->assertSame('60002543969', $history->raw_data['RESULTADO_TSE']['cand']['sqcand']);
    }

    public function test_it_updates_results_without_changing_cadastral_fields(): void
    {
        $history = $this->seedHistory();
        $candidateId = $history->candidate_id;
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $history->refresh();

        // Só campos de RESULTADO mudam; o cadastro fica como o sincronizador gravou.
        $this->assertSame('Eleito', $history->result_status);
        $this->assertSame($candidateId, $history->candidate_id);
        $this->assertSame(2026, $history->election_year);
        $this->assertSame(1, $history->round);
        $this->assertSame('CE', $history->state);
        $this->assertSame('Governador', $history->office_name);
        $this->assertSame('13', $history->ballot_number);
        $this->assertSame('PT', $history->party_acronym);
        $this->assertSame('PARTIDO DOS TRABALHADORES', $history->party_name);
        $this->assertSame('DEFERIDO', $history->candidacy_status);

        $candidate = Candidate::findOrFail($candidateId);
        $this->assertSame('ELMANO DE FREITAS', $candidate->ballot_name);
    }

    public function test_it_marks_a_first_round_elected_and_a_defeated_candidate(): void
    {
        $this->seedHistory();
        $this->seedHistory(['candidacy_external_id' => self::RIVAL_SQ]);
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $this->assertSame('Eleito', $this->history(self::MAIN_SQ)->result_status);

        // O TRAP CLÁSSICO: "Não eleito" NÃO pode virar eleito por substring.
        $this->assertSame('Não eleito', $this->history(self::RIVAL_SQ)->result_status);
    }

    public function test_it_marks_a_candidate_classified_for_the_second_round(): void
    {
        $this->seedHistory(['candidacy_external_id' => self::RIVAL_SQ]);

        $payload = $this->ea20();
        // RIVAL é o cand[1] do fixture (MAIN é cand[0]): classificar o candidato
        // semeado significa marcar a SUA entrada, não a do titular.
        $payload['carg'][0]['agr'][0]['par'][0]['cand'][1]['st'] = '2º turno';
        $payload['carg'][0]['agr'][0]['par'][0]['cand'][1]['e'] = 'n';
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($payload, 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $history = $this->history(self::RIVAL_SQ);

        $this->assertSame('2º turno', $history->result_status);
        $this->assertSame(1, $history->round);
    }

    public function test_it_updates_the_second_round_result_without_touching_the_first(): void
    {
        $urlRound2 = 'https://resultados.tse.jus.br/oficial/ele2026/6258/dados/br/br-c0001-e006258-u.json';

        $this->seedHistory(['office_name' => 'Presidente', 'state' => 'BR']);

        // `Http::fake()` ACUMULA stubs dentro do mesmo teste (nunca substitui),
        // então não se pode re-chamar `fakeResults` para mudar a resposta: o
        // primeiro stub registrado venceria e o 2º run leria o payload velho.
        // O payload é montado dentro de um closure sobre uma variável mutável.
        $vap = '50000000';

        Http::fake([
            '*oficial/comum/config/ele-c.json' => Http::response($this->elecConfig(), 200),
            $urlRound2 => function () use (&$vap) {
                return Http::response($this->ea20([
                    'ele' => '6258',
                    't' => '2',
                    'tpabr' => 'br',
                    'cdabr' => 'br',
                    'carg' => [$this->cargBlock('1', [[
                        'sqcand' => (string) self::MAIN_SQ,
                        'nm' => 'CANDIDATO A',
                        'st' => 'Eleito',
                        'e' => 's',
                        'vap' => $vap,
                        'pvap' => '55,00',
                    ]])],
                ]), 200);
            },
            '*' => Http::response([], 404),
        ]);

        // 1ª execução: cria a linha do 2º turno a partir da do 1º turno.
        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 2, '--url' => $urlRound2, '--office' => 'president'])
            ->expectsOutputToContain('2º turno — criados: 1')
            ->assertExitCode(0);

        $rows = CandidacyHistory::where('election_year', 2026)
            ->where('candidacy_external_id', self::MAIN_SQ)
            ->orderBy('round')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]->round);
        $this->assertNull($rows[0]->result_status); // histórico do 1º turno intacto
        $this->assertSame(2, $rows[1]->round);
        $this->assertSame('Eleito', $rows[1]->result_status);
        $this->assertSame('50000000', $rows[1]->raw_data['RESULTADO_TSE']['cand']['vap']);

        // 2ª execução com resultado novo: atualiza a MESMA linha do 2º turno.
        $vap = '51000000';

        Artisan::call('tse:import-results', ['--year' => 2026, '--round' => 2, '--url' => $urlRound2, '--office' => 'president']);

        $this->assertSame('51000000', $this->history(self::MAIN_SQ, 2)->raw_data['RESULTADO_TSE']['cand']['vap']);
        $this->assertSame(2, CandidacyHistory::where('election_year', 2026)->count());
    }

    public function test_it_creates_both_round_lines_when_only_the_candidate_exists(): void
    {
        // Sem NENHUMA linha de histórico, só o candidato (presidente/BR): o
        // pedido do turno 2 cria a base do 1º turno a partir dele e, em cima
        // dela, a linha do 2º turno — sem duplicar nem inventar candidato.
        $urlRound2 = 'https://resultados.tse.jus.br/oficial/ele2026/6258/dados/br/br-c0001-e006258-u.json';

        $candidate = $this->seedCandidate(['office_name' => 'PRESIDENTE', 'state' => 'BR']);

        Http::fake([
            '*oficial/comum/config/ele-c.json' => Http::response($this->elecConfig(), 200),
            $urlRound2 => Http::response($this->ea20([
                'ele' => '6258',
                't' => '2',
                'tpabr' => 'br',
                'cdabr' => 'BR',
                'carg' => [
                    $this->cargBlock('1', [
                        ['sqcand' => (string) self::MAIN_SQ, 'st' => 'Eleito'],
                    ]),
                ],
            ]), 200),
            '*' => Http::response([], 404),
        ]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 2, '--url' => $urlRound2, '--office' => 'president'])
            ->expectsOutputToContain('2º turno — criados: 1')
            ->assertExitCode(0);

        $round1 = $this->history(self::MAIN_SQ, 1);
        $round2 = $this->history(self::MAIN_SQ, 2);

        $this->assertNotNull($round1);
        $this->assertNotNull($round2);
        $this->assertSame($candidate->id, $round1->candidate_id);
        $this->assertSame($candidate->id, $round2->candidate_id);
        $this->assertSame('Eleito', $round2->result_status);
    }

    public function test_repeated_runs_never_duplicate_records(): void
    {
        $this->seedHistory();
        $this->seedHistory(['candidacy_external_id' => self::RIVAL_SQ]);
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Atualizadas: 0 | Inalteradas: 2')
            ->assertExitCode(0);

        // Idempotência real: contagem intacta e a 2ª execução não regravou.
        $this->assertSame(2, CandidacyHistory::count());
        $this->assertSame(1, CandidacyHistory::where('candidacy_external_id', self::MAIN_SQ)->count());
    }

    /**
     * Histórico de 2026 semeado — é assim que ele existe em produção (linhas de
     * eleições anteriores vindas do import CSV). O sincronizador cadastral NÃO
     * cria linha do ano corrente, então o importador só ATUALIZA estas.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function seedHistory(array $overrides = []): CandidacyHistory
    {
        $sq = (string) ($overrides['candidacy_external_id'] ?? self::MAIN_SQ);
        $office = $overrides['office_name'] ?? 'Governador';
        $state = $overrides['state'] ?? 'CE';

        $candidate = Candidate::create([
            'external_id' => (int) $sq,
            'state' => $state,
            'office_name' => $office,
            'civil_name' => 'CANDIDATO '.$sq,
            'ballot_name' => 'ELMANO DE FREITAS',
            'ballot_number' => '13',
            'party_acronym' => 'PT',
            'party_name' => 'PARTIDO DOS TRABALHADORES',
            'election_year' => 2026,
        ]);

        return CandidacyHistory::create([
            'candidate_id' => $candidate->id,
            'candidacy_external_id' => (int) $sq,
            'election_year' => 2026,
            'round' => 1,
            'state' => $state,
            'office_name' => $office,
            'ballot_number' => '13',
            'party_acronym' => 'PT',
            'party_name' => 'PARTIDO DOS TRABALHADORES',
            'candidacy_status' => 'DEFERIDO',
        ]);
    }

    /**
     * SÓ o candidato (sem histórico) — o cenário real de produção para o ano
     * corrente, já que o sync cadastral não cria linha de 2026.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function seedCandidate(array $overrides = []): Candidate
    {
        $sq = (string) ($overrides['candidacy_external_id'] ?? self::MAIN_SQ);

        return Candidate::create([
            'external_id' => (int) $sq,
            'state' => $overrides['state'] ?? 'CE',
            'office_name' => $overrides['office_name'] ?? 'GOVERNADOR',
            'civil_name' => 'CANDIDATO '.$sq,
            'ballot_name' => 'TITULAR '.$sq,
            'ballot_number' => '13',
            'party_acronym' => 'PT',
            'party_name' => 'PARTIDO DOS TRABALHADORES',
            'election_year' => 2026,
        ]);
    }

    private function history(int $sq, ?int $round = null): ?CandidacyHistory
    {
        return CandidacyHistory::where('election_year', 2026)
            ->where('candidacy_external_id', $sq)
            ->when($round !== null, fn ($q) => $q->where('round', $round))
            ->first();
    }

    /**
     * Payload EA20 no formato real: carg > agr > par > cand. Dois candidatos
     * por padrão (MAIN eleito, RIVAL não eleito), cargo governador (cd 3).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ea20(array $overrides = []): array
    {
        return array_replace([
            'ele' => '6259',
            't' => '1',
            'tpabr' => 'uf',
            'cdabr' => 'CE',
            'dg' => '04/10/2026',
            'hg' => '20:15:03',
            'tf' => 'f',
            'and' => '100',
            'carg' => [
                $this->cargBlock('3', [
                    ['sqcand' => (string) self::MAIN_SQ, 'n' => '13', 'nm' => 'ELMANO DE FREITAS', 'vap' => '3500000', 'pvap' => '63,42', 'st' => 'Eleito', 'e' => 's'],
                    ['sqcand' => (string) self::RIVAL_SQ, 'n' => '50', 'nm' => 'ADVERSÁRIO', 'vap' => '2000000', 'pvap' => '36,58', 'st' => 'Não eleito', 'e' => 'n'],
                ]),
            ],
        ], $overrides);
    }

    /**
     * Bloco `carg` (um cargo) com a cadeia agr > par > cand do EA20.
     *
     * @param  array<int, array<string, mixed>>  $cands
     * @return array<string, mixed>
     */
    private function cargBlock(string $cargoCd, array $cands): array
    {
        return [
            'cd' => $cargoCd,
            'agr' => [['par' => [['cand' => $cands]]]],
        ];
    }

    /**
     * Config EA11 (`ele-c.json`) mínimo: ciclo ele2026, turno 1, escopo UF CE,
     * cargos 1 e 3 — o que o resolvedor usa para montar a URL do EA20.
     *
     * @return array<string, mixed>
     */
    private function elecConfig(): array
    {
        return [
            'pl' => [[
                'c' => 'ele2026',
                'e' => [[
                    'cd' => 6259,
                    't' => 1,
                    'abr' => [[
                        'cd' => 'CE',
                        'cp' => [['cd' => '1'], ['cd' => '3']],
                    ]],
                ]],
            ]],
        ];
    }

    /**
     * Stub Http para EA11 (config) + EA20 (resultado). Catch-all no final
     * garante que nenhuma chamada cadastral escape sem querer.
     *
     * @param  array<string, Response>  $extra
     */
    private function fakeResults(array $extra = []): void
    {
        Http::fake(array_merge([
            '*oficial/comum/config/ele-c.json' => Http::response($this->elecConfig(), 200),
        ], $extra, [
            '*' => Http::response([], 404),
        ]));
    }

    public function test_it_preserves_the_previous_raw_data_and_merges_the_result(): void
    {
        $history = $this->seedHistory();
        $history->update(['raw_data' => ['origem' => 'csv-historico', 'id' => '60002543969']]);
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $raw = $history->refresh()->raw_data;

        // Merge seguro: chaves antigas intactas + namespace próprio do TSE.
        $this->assertSame('csv-historico', $raw['origem']);
        $this->assertSame('60002543969', $raw['id']);
        $this->assertSame('Eleito', $raw['RESULTADO_TSE']['cand']['st']);
        $this->assertArrayHasKey('RESULTADO_TSE_EM', $raw);
    }

    public function test_it_counts_candidacies_without_a_match_and_creates_nothing(): void
    {
        // Nenhum candidato semeado: toda candidatura do JSON vira
        // "sem correspondência" e NADA é criado (nem histórico, nem candidato).
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Sem correspondência no banco: 2')
            ->assertExitCode(0);

        $this->assertSame(0, CandidacyHistory::count());
        $this->assertSame(0, Candidate::count());
    }

    public function test_it_creates_the_history_when_only_the_candidate_exists(): void
    {
        // Só o candidato existe (cenário real: sync cadastral não cria
        // histórico do ano corrente). O importador CRIA a linha do turno 1
        // a partir dele — com candidate_id, dados cadastrais copiados e a
        // situação oficial — em vez de "sem correspondência".
        $candidate = $this->seedCandidate();

        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Históricos criados: 1')
            ->expectsOutputToContain('Sem correspondência no banco: 1')
            ->assertExitCode(0);

        $history = $this->history(self::MAIN_SQ);
        $this->assertNotNull($history);
        $this->assertSame($candidate->id, $history->candidate_id);
        $this->assertSame(2026, $history->election_year);
        $this->assertSame(1, $history->round);
        $this->assertSame('CE', $history->state);
        $this->assertSame('GOVERNADOR', $history->office_name);
        $this->assertSame('13', $history->ballot_number);
        $this->assertSame('PT', $history->party_acronym);
        $this->assertSame('Eleito', $history->result_status);
        $this->assertSame('Eleito', $history->raw_data['RESULTADO_TSE']['cand']['st']);
    }

    public function test_it_does_not_create_histories_for_unknown_candidates(): void
    {
        // MAIN existe; RIVAL (sq 60002531351) NÃO — só o conhecido ganha linha.
        $this->seedCandidate();

        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $this->assertSame(1, CandidacyHistory::count());
        $this->assertNotNull($this->history(self::MAIN_SQ));
        $this->assertNull($this->history(self::RIVAL_SQ));
    }

    public function test_it_rejects_a_candidate_with_an_incompatible_scope(): void
    {
        // Candidato de SP no arquivo de CE: escopo incompatível, nada criado.
        $this->seedCandidate(['state' => 'SP']);

        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Escopo incompatível: 1')
            ->assertExitCode(0);

        $this->assertSame(0, CandidacyHistory::count());
    }

    public function test_a_second_run_updates_without_duplicating(): void
    {
        $this->seedCandidate();

        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $this->assertSame(1, CandidacyHistory::count());

        // Reexecução com o MESMO payload: idempotente — inalterada, sem linha nova.
        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Inalteradas: 1')
            ->assertExitCode(0);

        $this->assertSame(1, CandidacyHistory::count());
    }

    #[DataProvider('officialStatuses')]
    public function test_it_normalizes_the_official_statuses(string $tse, string $canonical): void
    {
        $this->seedHistory();

        $payload = $this->ea20();
        $payload['carg'][0]['agr'][0]['par'][0]['cand'][0]['st'] = $tse;
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($payload, 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        $this->assertSame($canonical, $this->history(self::MAIN_SQ)->result_status);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function officialStatuses(): array
    {
        return [
            'eleito' => ['Eleito', 'Eleito'],
            'nao eleito (minúsculo)' => ['não eleito', 'Não eleito'],
            'eleito por QP' => ['Eleito por QP', 'Eleito por QP'],
            'eleito por media (sem acento)' => ['Eleito por media', 'Eleito por média'],
            'segundo turno' => ['segundo turno', '2º turno'],
        ];
    }

    public function test_it_preserves_an_unknown_status_literally(): void
    {
        $this->seedHistory();

        $payload = $this->ea20();
        $payload['carg'][0]['agr'][0]['par'][0]['cand'][0]['st'] = 'Aguardando julgamento definitivo';
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($payload, 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Situações desconhecidas: 1')
            ->assertExitCode(0);

        $this->assertSame('Aguardando julgamento definitivo', $this->history(self::MAIN_SQ)->result_status);
    }

    #[DataProvider('httpFailures')]
    public function test_it_reports_http_failures_by_kind(int $status): void
    {
        $this->seedHistory();
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response('erro', $status)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Arquivos lidos: 0')
            ->assertExitCode(0);

        $this->assertNull($this->history(self::MAIN_SQ)->result_status);
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function httpFailures(): array
    {
        return ['403' => [403], '404' => [404], '429' => [429], '500' => [500]];
    }

    public function test_it_reports_a_timeout(): void
    {
        $this->seedHistory();
        $this->fakeResults([self::URL_GOV_CE_R1 => fn () => throw new ConnectionException('timeout simulado')]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Falhas HTTP/JSON: 1 (timeout=1)')
            ->assertExitCode(0);

        $this->assertNull($this->history(self::MAIN_SQ)->result_status);
    }

    public function test_it_rejects_html_masquerading_as_json(): void
    {
        $this->seedHistory();
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response('<html>manutenção</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Falhas HTTP/JSON: 1 (html=1)')
            ->assertExitCode(0);
    }

    public function test_it_rejects_invalid_json(): void
    {
        $this->seedHistory();
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response('{isso não é json', 200, ['Content-Type' => 'application/json'])]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Falhas HTTP/JSON: 1 (json=1)')
            ->assertExitCode(0);
    }

    public function test_it_reports_a_json_without_candidates(): void
    {
        $this->seedHistory();
        $empty = $this->ea20();
        $empty['carg'][0]['agr'][0]['par'][0]['cand'] = [];
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($empty, 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->expectsOutputToContain('Sem candidaturas: 1')
            ->assertExitCode(0);
    }

    public function test_it_respects_the_office_filter(): void
    {
        $this->seedHistory();
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        // O arquivo é de governador (cargo 3), mas o filtro pede president
        // (cargo 1): as 2 candidaturas caem em "fora do filtro". --url mantém o
        // arquivo do governador sem o resolvedor trocar a URL pela do
        // presidente.
        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--office' => 'president', '--url' => self::URL_GOV_CE_R1])
            ->expectsOutputToContain('Fora do filtro: 2')
            ->assertExitCode(0);

        $this->assertNull($this->history(self::MAIN_SQ)->result_status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        // Os DOIS candidatos do EA20 precisam existir para ambos contarem como
        // "atualizadas" — senão o RIVAL viraria "sem correspondência".
        $this->seedHistory();
        $this->seedHistory(['candidacy_external_id' => self::RIVAL_SQ]);
        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor', '--dry-run' => true])
            ->expectsOutputToContain('Atualizadas: 2')
            ->expectsOutputToContain('DRY-RUN: nenhuma alteração foi gravada no banco.')
            ->assertExitCode(0);

        $this->assertNull($this->history(self::MAIN_SQ)->result_status);
        $this->assertArrayNotHasKey('RESULTADO_TSE', $this->history(self::MAIN_SQ)->raw_data ?? []);
    }

    public function test_it_preserves_histories_from_previous_elections(): void
    {
        $current = $this->seedHistory();

        // Histórico de eleição anterior (2022) do MESMO candidato — não pode
        // ser tocado por uma importação de resultados de 2026.
        $past = CandidacyHistory::create([
            'candidate_id' => $current->candidate_id,
            'candidacy_external_id' => self::MAIN_SQ,
            'election_year' => 2022,
            'round' => 1,
            'state' => 'CE',
            'office_name' => 'Governador',
            'ballot_number' => '13',
            'result_status' => 'Eleito',
            'raw_data' => ['origem' => 'csv'],
        ]);

        $this->fakeResults([self::URL_GOV_CE_R1 => Http::response($this->ea20(), 200)]);

        $this->artisan('tse:import-results', ['--year' => 2026, '--round' => 1, '--state' => 'CE', '--office' => 'governor'])
            ->assertExitCode(0);

        // 2022 intacto; 2026 atualizado. O escopo do ano isola as escritas.
        $past->refresh();
        $this->assertSame('Eleito', $past->result_status);
        $this->assertSame(['origem' => 'csv'], $past->raw_data);
        $this->assertSame('Eleito', $current->refresh()->result_status);
    }

    public function test_the_cadastral_sync_still_works_after_the_results_importer(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', ['--uf' => 'CE', '--year' => 2026, '--office' => 'governor'])
            ->assertExitCode(0);

        $candidate = Candidate::firstOrFail();

        $this->assertSame(60002543969, (int) $candidate->external_id);
        $this->assertSame('GOVERNADOR', $candidate->office_name);
    }
}
