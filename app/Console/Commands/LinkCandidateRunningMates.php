<?php

namespace App\Console\Commands;

use App\Services\Tse\CandidateSyncService;
use Illuminate\Console\Command;

/**
 * Refaz os vínculos de vice/suplente a partir de `vices` que a API do TSE
 * deixou em `candidates.raw_data`.
 *
 * Antes, o vinculo vinha de SQ_COLIGACAO + round, campos que existiam só no
 * CSV de Dados Abertos — os dois sumiram com a migração para a API. A
 * estrutura continua a mesma: o vice aponta para o titular por
 * `running_mate_of_id`, e o titular segue com `running_mate_of_id` null.
 *
 * O `sync:candidates-tse` já faz esse passo ao final; este comando existe
 * para refazer os vínculos sem precisar chamar a API do TSE de novo.
 */
class LinkCandidateRunningMates extends Command
{
    // comando para rodar:
    // php artisan candidates:link-running-mates --uf=CE --year=2026

    protected $signature = 'candidates:link-running-mates
        {--year=2026 : Ano da eleição}
        {--uf= : UF (padrão: todas)}';

    protected $description = 'Vincula vice-governadores e suplentes de senador ao titular usando os vices da API do TSE';

    public function handle(CandidateSyncService $sync): int
    {
        $year = (int) $this->option('year');
        $uf = $this->option('uf');

        $linked = $sync->linkRunningMatesFor($year, $uf !== '' ? strtoupper((string) $uf) : null);

        $this->info("{$linked} vínculo(s) criado(s).");

        return self::SUCCESS;
    }
}
