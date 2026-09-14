<?php

namespace App\Services\News;

class LinkNormalizer
{
    private const PARAMETROS_DESCARTAVEIS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'itok',
    ];

    public function normalizar(string $url): string
    {
        $partes = parse_url(trim($url));

        if ($partes === false || empty($partes['host'])) {
            return rtrim(strtolower(trim($url)), '/');
        }

        $host = strtolower(preg_replace('/^www\./', '', $partes['host']));
        $path = rtrim($partes['path'] ?? '', '/');

        $query = [];
        if (!empty($partes['query'])) {
            parse_str($partes['query'], $query);
            foreach (self::PARAMETROS_DESCARTAVEIS as $param) {
                unset($query[$param]);
            }
            ksort($query);
        }

        $normalizado = 'https://' . $host . $path;

        if (!empty($query)) {
            $normalizado .= '?' . http_build_query($query);
        }

        return $normalizado;
    }
}
