<?php

namespace App\Domains\Radar\Services;

/**
 * Cópia dos indicadores oficiais do IBGE guardada no repositório
 * (resources/radar/ibge-snapshot.json).
 *
 * Em produção o servidor às vezes não consegue abrir conexão com
 * servicodados.ibge.gov.br ("Connection timed out", mesmo com 30s), e sem
 * resultado publicado a página ficava para sempre em "indicadores sendo
 * preparados". Com a cópia, a página mostra valores reais do IBGE na hora, com
 * a data da consulta; a atualização semanal (ver RadarRefresher) publica o
 * resultado novo assim que a consulta funcionar, e ele passa a ter prioridade.
 */
class IbgeSnapshot
{
    public static function load(): ?array
    {
        $path = resource_path('radar/ibge-snapshot.json');

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['indicators']) || empty($data['generated_at'])) {
            return null;
        }

        return $data;
    }
}
