<?php

namespace App\Domains\Radar\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Baixa os dois arquivos anuais oficiais da Câmara para uma pasta temporária
 * própria, agrega e apaga a pasta. Só lê fontes oficiais e devolve o
 * resultado — quem decide publicar é o RadarRefresher.
 */
class CamaraRadarCollector
{
    public function __construct(
        private CamaraBulkFileDownloader $downloader,
        private CamaraTopicAggregator $aggregator,
    ) {
    }

    /**
     * Baixa os CSVs de temas e autores, agrega as contagens do ano e retorna
     * também metadados dos arquivos. O diretório temporário é removido no
     * finally mesmo se download ou leitura lançar uma exceção.
     */
    public function collect(int $year): array
    {
        $dir = storage_path('app/radar-tmp/' . Str::uuid());
        File::ensureDirectoryExists($dir);

        try {
            $files = [];

            foreach (['themes' => 'topics_url', 'authors' => 'authors_url'] as $kind => $configKey) {
                $url = str_replace('{year}', (string) $year, config("radar.camara.{$configKey}"));
                $download = $this->downloader->download($url, "{$dir}/{$kind}.csv");

                $files[$kind] = [
                    'url' => $url,
                    'bytes' => $download['bytes'],
                    'last_modified' => $download['last_modified'],
                    'resumed_attempts' => $download['attempts'],
                ];
            }

            $result = $this->aggregator->aggregate(
                "{$dir}/themes.csv",
                "{$dir}/authors.csv",
                $year,
                config('radar.bill_types'),
                (string) config('radar.camara.deputy_author_type'),
            );

            return $result + ['files' => $files];
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
