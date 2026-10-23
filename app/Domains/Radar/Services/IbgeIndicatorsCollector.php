<?php

namespace App\Domains\Radar\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Indicadores oficiais do IBGE (API de agregados/SIDRA) para as 27 UFs, uma
 * chamada por indicador. Cada indicador guarda fonte, tabela, variável,
 * unidade e o período de referência que o IBGE devolveu. Valor ausente ou
 * não numérico ('-', '...', 'X') lança exceção: nada é inventado.
 */
class IbgeIndicatorsCollector
{
    public function collect(): array
    {
        $base = rtrim(config('radar.ibge.base_url'), '/');
        $indicators = [];

        foreach (config('radar.ibge.indicators') as $key => $def) {
            $url = "{$base}/{$def['aggregate']}/periodos/{$def['period']}/variaveis/{$def['variable']}";

            // Do Render, a conexão com servicodados.ibge.gov.br às vezes passa
            // dos 10s padrão (visto em produção: "Connection timed out after
            // 10002 milliseconds"). Mais tempo para conectar e mais tentativas.
            $response = Http::connectTimeout(30)
                ->timeout(90)
                ->retry([5000, 15000, 30000], throw: false)
                ->acceptJson()
                ->get($url, ['localidades' => 'N3[all]']);

            if (! $response->successful()) {
                throw new RuntimeException("IBGE {$key}: HTTP {$response->status()}");
            }

            $variable = $response->json('0');
            $series = $variable['resultados'][0]['series'] ?? null;

            if (! is_array($series)) {
                throw new RuntimeException("IBGE {$key}: resposta sem séries");
            }

            $values = [];
            $periods = [];

            foreach ($series as $item) {
                $uf = RadarUfs::byIbgeCode($item['localidade']['id'] ?? 0);
                $serie = $item['serie'] ?? [];

                if ($uf === null || count($serie) !== 1) {
                    throw new RuntimeException("IBGE {$key}: localidade ou série inesperada");
                }

                $period = (string) array_key_first($serie);
                $raw = $serie[$period];

                if (! is_numeric($raw)) {
                    throw new RuntimeException("IBGE {$key}: valor ausente para {$uf} ({$raw})");
                }

                $values[$uf] = $raw + 0;
                $periods[$period] = true;
            }

            if (count($periods) !== 1) {
                throw new RuntimeException("IBGE {$key}: UFs com períodos diferentes");
            }

            ksort($values);

            $indicators[$key] = [
                'label' => $def['label'],
                'source' => $def['source'],
                'unit' => $variable['unidade'] ?? null,
                'period' => (string) array_key_first($periods),
                'aggregate' => $def['aggregate'],
                'variable' => $def['variable'],
                'url' => $url . '?localidades=N3[all]',
                'values' => $values,
            ];
        }

        return ['indicators' => $indicators];
    }
}
