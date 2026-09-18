<?php

namespace App\Console\Commands;

use App\Models\Legislator;
use App\Services\SenateApiService;
use Illuminate\Console\Command;
use App\Enums\ElectoralStatus;
use App\Enums\LegislatorStatus;

class SyncSenateLegislators extends Command
{
    protected $signature = 'sync:legislators-senate';
    protected $description = 'Fetch senators from Senado Federal API and save to database';

    public function handle(SenateApiService $api)
    {
        $parliamentarians = $api->listParliamentarians('CE');
        $this->info('Found ' . count($parliamentarians) . ' senators to sync.');
        $bar = $this->output->createProgressBar(count($parliamentarians));

        foreach ($parliamentarians as $p) {
            try {
                $identification = $p['IdentificacaoParlamentar'] ?? [];
                $details = $api->getDetails($identification['CodigoParlamentar']);
                $mandate = $api->getMandate($identification['CodigoParlamentar']) ?? [];

                $isAlternate = str_contains(strtolower(trim($mandate['DescricaoParticipacao'] ?? '')), 'suplente');

                $electoralStatus = match(true) {
                    $isAlternate => ElectoralStatus::Alternate,
                    str_contains(strtolower(trim($mandate['DescricaoParticipacao'] ?? '')), 'titular') => ElectoralStatus::Sitting,
                    default => ElectoralStatus::Unknown,
                };

                Legislator::updateOrCreate(
                    ['external_id' => $identification['CodigoParlamentar'], 'chamber' => 'senate'],
                    [
                        'civil_name' => $identification['NomeCompletoParlamentar'] ?? null,
                        'parliamentary_name' => $identification['NomeParlamentar'] ?? null,
                        'cpf' => null,
                        'photo_url' => str_replace('http://', 'https://', $identification['UrlFotoParlamentar'] ?? null),
                        'party' => $identification['SiglaPartidoParlamentar'] ?? null,
                        'state' => $identification['UfParlamentar'] ?? null,
                        'legislature' => $api->currentLegislatureNumber($mandate),
                        'electoral_status' => $electoralStatus,
                        'status' => $api->determineStatus($mandate, $isAlternate),
                        'phone' => $details['Telefones']['Telefone'][0]['NumeroTelefone'] ?? null,
                        'email' => $identification['EmailParlamentar'] ?? null,
                        'official_website' => $identification['UrlPaginaParticular'] ?? null,
                        'social_media' => [],
                        'raw_data' => array_merge($details, ['Mandato' => $mandate]),
                    ]
                );
            } catch (\Throwable $e) {
                $this->error("Failed to sync senator {$identification['CodigoParlamentar']}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sync completed.');
    }
}
