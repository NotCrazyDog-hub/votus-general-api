<?php

namespace App\Domains\Radar\Services;

/**
 * As 27 unidades federativas com o código oficial do IBGE (o mesmo `id` da
 * API de localidades e das malhas do IBGE, e o `localidade.id` do SIDRA).
 * Lista fixa de propósito: não muda, e assim o Radar não depende de uma
 * chamada extra ao IBGE só para saber nomes e regiões.
 */
class RadarUfs
{
    public const ALL = [
        'RO' => ['ibge_code' => 11, 'name' => 'Rondônia', 'region' => 'Norte'],
        'AC' => ['ibge_code' => 12, 'name' => 'Acre', 'region' => 'Norte'],
        'AM' => ['ibge_code' => 13, 'name' => 'Amazonas', 'region' => 'Norte'],
        'RR' => ['ibge_code' => 14, 'name' => 'Roraima', 'region' => 'Norte'],
        'PA' => ['ibge_code' => 15, 'name' => 'Pará', 'region' => 'Norte'],
        'AP' => ['ibge_code' => 16, 'name' => 'Amapá', 'region' => 'Norte'],
        'TO' => ['ibge_code' => 17, 'name' => 'Tocantins', 'region' => 'Norte'],
        'MA' => ['ibge_code' => 21, 'name' => 'Maranhão', 'region' => 'Nordeste'],
        'PI' => ['ibge_code' => 22, 'name' => 'Piauí', 'region' => 'Nordeste'],
        'CE' => ['ibge_code' => 23, 'name' => 'Ceará', 'region' => 'Nordeste'],
        'RN' => ['ibge_code' => 24, 'name' => 'Rio Grande do Norte', 'region' => 'Nordeste'],
        'PB' => ['ibge_code' => 25, 'name' => 'Paraíba', 'region' => 'Nordeste'],
        'PE' => ['ibge_code' => 26, 'name' => 'Pernambuco', 'region' => 'Nordeste'],
        'AL' => ['ibge_code' => 27, 'name' => 'Alagoas', 'region' => 'Nordeste'],
        'SE' => ['ibge_code' => 28, 'name' => 'Sergipe', 'region' => 'Nordeste'],
        'BA' => ['ibge_code' => 29, 'name' => 'Bahia', 'region' => 'Nordeste'],
        'MG' => ['ibge_code' => 31, 'name' => 'Minas Gerais', 'region' => 'Sudeste'],
        'ES' => ['ibge_code' => 32, 'name' => 'Espírito Santo', 'region' => 'Sudeste'],
        'RJ' => ['ibge_code' => 33, 'name' => 'Rio de Janeiro', 'region' => 'Sudeste'],
        'SP' => ['ibge_code' => 35, 'name' => 'São Paulo', 'region' => 'Sudeste'],
        'PR' => ['ibge_code' => 41, 'name' => 'Paraná', 'region' => 'Sul'],
        'SC' => ['ibge_code' => 42, 'name' => 'Santa Catarina', 'region' => 'Sul'],
        'RS' => ['ibge_code' => 43, 'name' => 'Rio Grande do Sul', 'region' => 'Sul'],
        'MS' => ['ibge_code' => 50, 'name' => 'Mato Grosso do Sul', 'region' => 'Centro-Oeste'],
        'MT' => ['ibge_code' => 51, 'name' => 'Mato Grosso', 'region' => 'Centro-Oeste'],
        'GO' => ['ibge_code' => 52, 'name' => 'Goiás', 'region' => 'Centro-Oeste'],
        'DF' => ['ibge_code' => 53, 'name' => 'Distrito Federal', 'region' => 'Centro-Oeste'],
    ];

    public static function isValid(string $uf): bool
    {
        return isset(self::ALL[strtoupper($uf)]);
    }

    public static function byIbgeCode(int|string $code): ?string
    {
        foreach (self::ALL as $uf => $info) {
            if ($info['ibge_code'] === (int) $code) {
                return $uf;
            }
        }

        return null;
    }
}
