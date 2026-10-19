<?php

namespace App\Console\Commands;

use App\Enums\CandidateOffice;
use App\Services\Tse\TseElectionResultsService;
use App\Services\Tse\TseResultsException;
use Illuminate\Console\Command;

/**
 * Atualiza os RESULTADOS eleitorais (apuração) em `candidacy_histories`,
 * sem tocar na sincronização cadastral (`sync:candidates-tse`).
 *
 * Fonte: JSONs EA20 (`-u.json`) de resultados.tse.jus.br, consultados por
 * Http (estáticos — sem Playwright); códigos de eleição/turno vêm do EA11.
 * Idempotente e pensado para rodar repetidamente durante a apuração; com
 * `--dry-run` analisa tudo sem gravar nada.
 *
 * Exemplos:
 *
 *   php artisan tse:import-results --year=2026 --round=1
 *   php artisan tse:import-results --year=2026 --round=1 --state=CE --office=governor
 *   php artisan tse:import-results --year=2026 --round=1 --state=CE --office=governor --dry-run
 */
class TseImportResults extends Command
{
    protected $signature = 'tse:import-results
        {--year=2026 : Ano da eleição}
        {--round=1 : Turno a consultar (1 ou 2)}
        {--state= : UF limitante (ex.: CE); necessário para cargos estaduais}
        {--office= : Cargo limitante (president|governor|senator|federal_deputy|state_deputy)}
        {--dry-run : Analisa e contabiliza sem gravar no banco}
        {--url= : URL específica de um arquivo EA20 (diagnóstico/testes)}';

    protected $description = 'Importa os resultados oficiais do TSE (EA20) para candidacy_histories sem refazer a sincronização cadastral';

    public function handle(TseElectionResultsService $results): int
    {
        $year = (int) $this->option('year');
        $round = (int) $this->option('round');
        $dryRun = (bool) $this->option('dry-run');
        $state = strtoupper(trim((string) $this->option('state')));
        $state = $state === '' ? null : $state;

        if (! in_array($round, [1, 2], true)) {
            $this->error('Turno inválido. Use --round=1 ou --round=2.');

            return self::FAILURE;
        }

        if ($state !== null && ! preg_match('/^[A-Z]{2}$/', $state)) {
            $this->error('UF inválida. Use a sigla de 2 letras (ex.: CE).');

            return self::FAILURE;
        }

        $office = null;
        if ($this->option('office') !== null && $this->option('office') !== '') {
            $office = CandidateOffice::tryFrom((string) $this->option('office'));
            if ($office === null) {
                $this->error('Cargo inválido. Use: '.implode('|', array_column(CandidateOffice::cases(), 'value')));

                return self::FAILURE;
            }
        }

        $this->info(sprintf(
            'Importando resultados — ano %d, turno %d%s%s%s...',
            $year,
            $round,
            $state ? ", UF {$state}" : '',
            $office ? ", cargo {$office->value}" : '',
            $dryRun ? ' [DRY-RUN]' : '',
        ));

        try {
            $urlOption = (string) $this->option('url');
            $resolved = $urlOption !== ''
                ? ['urls' => [$urlOption], 'notes' => ['--url manual: códigos de eleição não foram validados pelo EA11.']]
                : $results->resolveFileUrls($year, $round, $state, $office);
        } catch (TseResultsException $e) {
            $this->error(ucfirst($e->kind()).": {$e->getMessage()}");

            return self::FAILURE;
        }

        foreach ($resolved['notes'] as $note) {
            $this->line("  · {$note}");
        }

        if ($resolved['urls'] === []) {
            $this->warn('Nenhum arquivo a consultar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('URLs consultadas:');
        foreach ($resolved['urls'] as $url) {
            $this->line("  → {$url}");
        }
        $this->newLine();

        $stats = $this->emptyStats();
        $failures = [];

        foreach ($resolved['urls'] as $url) {
            try {
                $file = $results->fetchResultFile($url);
                $meta = $file['meta'];
                $stats['arquivos_ok']++;

                if ($meta['t'] !== null && (int) $meta['t'] !== $round) {
                    // Nunca misturar turnos: --url pode apontar para o
                    // arquivo de outra rodada.
                    $stats['turno_inesperado']++;
                    $this->warn("  ! {$url} é do turno {$meta['t']} (esperado {$round}) — ignorado.");

                    continue;
                }

                $extracted = $results->extractCandidates($file['json']);
                $extracted['meta'] = $meta;
                $stats['duplicadas'] += $extracted['duplicates'];

                if ($extracted['candidates'] === []) {
                    $stats['sem_candidaturas']++;
                    $this->line("  · {$url}: JSON válido, nenhuma candidatura encontrada.");

                    continue;
                }

                $stats = $results->applyResults($extracted, $year, $round, $office, $dryRun, $stats);
            } catch (TseResultsException $e) {
                $failures[$e->kind()] = ($failures[$e->kind()] ?? 0) + 1;
                $this->warn('  ! '.ucfirst($e->kind()).": {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->renderStats($stats, $failures, $dryRun);

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return [
            'arquivos_ok' => 0,
            'sem_candidaturas' => 0,
            'turno_inesperado' => 0,
            'duplicadas' => 0,
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
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $failures
     */
    private function renderStats(array $stats, array $failures, bool $dryRun): void
    {
        $falhas = array_sum($failures);
        $detalhe = $falhas > 0 ? ' ('.collect($failures)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').')' : '';

        $this->info('Resumo:');
        $this->line("  Arquivos lidos: {$stats['arquivos_ok']} | Falhas HTTP/JSON: {$falhas}{$detalhe}");
        $this->line("  Candidaturas encontradas: {$stats['candidaturas']} | Duplicadas ignoradas: {$stats['duplicadas']} | Sem candidaturas: {$stats['sem_candidaturas']}");
        $this->line("  Atualizadas: {$stats['atualizadas']} | Inalteradas: {$stats['inalteradas']} | Históricos criados: {$stats['historicos_criados']}");
        $this->line("  Sem correspondência no banco: {$stats['sem_correspondencia']} | Escopo incompatível: {$stats['escopo_incompativel']} | Sem situação: {$stats['sem_situacao']} | Fora do filtro: {$stats['fora_do_escopo']}");
        $this->line("  2º turno — criados: {$stats['round2_criados']} | atualizados: {$stats['round2_atualizados']}");
        $this->line("  Situações desconhecidas: {$stats['situacoes_desconhecidas']} | Erros individuais: {$stats['erros']} | Turno inesperado: {$stats['turno_inesperado']}");

        if ($dryRun) {
            $this->warn('DRY-RUN: nenhuma alteração foi gravada no banco.');
        }
    }
}
