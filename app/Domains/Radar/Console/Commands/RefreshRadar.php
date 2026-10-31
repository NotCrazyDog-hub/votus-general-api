<?php

namespace App\Domains\Radar\Console\Commands;

use App\Domains\Radar\Services\RadarRefresher;
use App\Domains\Radar\Services\RadarUfs;
use Illuminate\Console\Command;
use Throwable;

class RefreshRadar extends Command
{
    protected $signature = 'radar:refresh
        {source : camara, senado ou ibge}
        {scope?* : anos (camara) ou UFs (senado); vazio = todos}
        {--dry-run : só calcula, valida e imprime — não grava nada no cache}
        {--output= : com --dry-run, salva o resultado completo em JSON neste arquivo}
        {--force : atualiza mesmo se o resultado publicado ainda estiver em dia}';

    protected $description = 'Atualiza (ou simula, com --dry-run) os dados do Radar do Brasil a partir das fontes oficiais';

    /**
     * Atualiza e valida uma fonte e escopo do radar; com --dry-run calcula os dados sem publicá-los.
     */

    public function handle(RadarRefresher $refresher)
    {
        $source = $this->argument('source');

        $scopes = $this->argument('scope') ?: match ($source) {
            'camara' => RadarRefresher::years(),
            'senado' => array_keys(RadarUfs::ALL),
            'ibge' => ['all'],
            default => [],
        };

        if ($scopes === []) {
            $this->error('Fonte inválida. Use: camara, senado ou ibge');

            return self::FAILURE;
        }

        $failed = false;
        $outputs = [];

        foreach ($scopes as $scope) {
            $this->info("== {$source}:{$scope}");

            if (! $this->option('dry-run')) {
                $status = $refresher->refresh($source, $scope, (bool) $this->option('force'));
                $this->line("   {$status}");
                $failed = $failed || $status === 'failed';
                continue;
            }

            try {
                $result = $refresher->compute($source, $scope);
                $outputs["{$source}:{$scope}"] = $result;
                $this->printSummary($result);
            } catch (Throwable $e) {
                $this->error("   falhou: {$e->getMessage()}");
                $failed = true;
            }
        }

        if ($this->option('dry-run') && $this->option('output')) {
            file_put_contents($this->option('output'), json_encode($outputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Resultado salvo em ' . $this->option('output'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Imprime contagens de validação diferentes conforme a fonte processada no dry-run. */
    private function printSummary(array $result): void
    {
        match ($result['source']) {
            'camara' => $this->line(sprintf(
                '   %d proposições com deputado autor, %d UFs; checks: %s',
                $result['totals']['bills_with_deputy_author'],
                $result['totals']['states_with_data'],
                json_encode($result['checks']),
            )),
            'senado' => $this->line(sprintf(
                '   %d senadores, %d proposições desde %s, %d com falha de detalhe',
                count($result['senators']),
                $result['legislature']['propositions'],
                $result['since'],
                $result['checks']['details_failed'],
            )),
            'ibge' => collect($result['indicators'])->each(fn ($i, $key) => $this->line(
                "   {$key}: período {$i['period']}, " . count($i['values']) . ' UFs',
            )),
        };
    }
}
