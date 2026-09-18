<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use Illuminate\Console\Command;

class LinkCandidateRunningMates extends Command
{
    // comando para rodar:
    // 'php artisan candidates:link-running-mates --year=2026 --uf=CE'
    protected $signature = 'candidates:link-running-mates
        {--year=2026 : Ano da eleição}
        {--uf=CE : UF}';

    protected $description = 'Vincula vice-governadores e suplentes de senador ao titular da mesma chapa (via SQ_COLIGACAO)';

    protected array $tickets = [
        'GOVERNADOR' => ['VICE-GOVERNADOR'],
        'SENADOR' => ['1º SUPLENTE', '2º SUPLENTE'],
    ];

    public function handle()
    {
        $year = (int) $this->option('year');
        $uf = $this->option('uf');
        $linked = 0;
        $notFound = [];

        foreach ($this->tickets as $titularOffice => $runningMateOffices) {
            $titulares = Candidate::where('election_year', $year)
                ->where('state', $uf)
                ->where('office_name', $titularOffice)
                ->get();

            foreach ($titulares as $titular) {
                $coligacao = $titular->raw_data['SQ_COLIGACAO'] ?? null;

                if (!$coligacao) {
                    $notFound[] = "{$titular->ballot_name} ({$titularOffice}) sem SQ_COLIGACAO no raw_data";
                    continue;
                }

                $runningMates = Candidate::where('election_year', $year)
                    ->where('state', $uf)
                    ->whereIn('office_name', $runningMateOffices)
                    ->where('round', $titular->round)
                    ->get()
                    ->filter(fn ($c) => ($c->raw_data['SQ_COLIGACAO'] ?? null) === $coligacao);

                foreach ($runningMates as $mate) {
                    $mate->update(['running_mate_of_id' => $titular->id]);
                    $linked++;
                }

                if ($runningMates->isEmpty()) {
                    $notFound[] = "{$titular->ballot_name} ({$titularOffice}) sem vice/suplente encontrado pra coligação {$coligacao}";
                }
            }
        }

        $this->info("{$linked} vínculo(s) criado(s).");

        if (!empty($notFound)) {
            $this->warn(count($notFound) . ' titular(es) sem vínculo completo:');
            foreach ($notFound as $item) {
                $this->line("  - {$item}");
            }
        }

        return self::SUCCESS;
    }
}