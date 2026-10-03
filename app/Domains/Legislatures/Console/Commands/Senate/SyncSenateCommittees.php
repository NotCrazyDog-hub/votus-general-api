<?php

namespace App\Domains\Legislatures\Console\Commands\Senate;

use App\Domains\Legislatures\Models\Committee;
use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Services\Senate\SenateApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSenateCommittees extends Command
{
    protected $signature = 'sync:committees-senate';
    protected $description = 'Fetch and store current committee memberships for already-synced CE senators';

    public function handle(SenateApiService $api)
    {
        $legislators = Legislator::where('chamber', 'senate')->where('state', 'CE')->get();
        $this->info('Syncing committees for ' . $legislators->count() . ' senators.');
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            try {
                $comissoes = $api->getCommittees($legislator->external_id);

                foreach ($comissoes as $c) {
                    $identificacao = $c['IdentificacaoComissao'] ?? [];

                    $committee = Committee::updateOrCreate(
                        ['external_id' => $identificacao['CodigoComissao'], 'chamber' => 'senate'],
                        [
                            'name' => $identificacao['NomeComissao'] ?? null,
                            'acronym' => $identificacao['SiglaComissao'] ?? null,
                        ]
                    );

                    DB::table('committee_legislator')->updateOrInsert(
                        ['legislator_id' => $legislator->id, 'committee_id' => $committee->id],
                        [
                            'role' => $c['DescricaoParticipacao'] ?? null,
                            'start_date' => $c['DataInicio'] ?? null,
                            'end_date' => $c['DataFim'] ?? null,
                            'updated_at' => now(),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                $this->error("Failed to sync committees for senator {$legislator->external_id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Committee sync completed.');
    }
}