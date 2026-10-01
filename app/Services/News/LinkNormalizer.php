<?php

namespace App\Services\News;

class LinkNormalizer
{
    private const PARAMETROS_DESCARTAVEIS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'itok',
    ];

    public function normalize(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || empty($parts['host'])) {
            return rtrim(strtolower(trim($url)), '/');
        }

        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = rtrim($parts['path'] ?? '', '/');

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (self::PARAMETROS_DESCARTAVEIS as $param) {
                unset($query[$param]);
            }
            ksort($query);
        }

        $normalized = 'https://' . $host . $path;

        if (!empty($query)) {
            $normalized .= '?' . http_build_query($query);
        }

        return $normalized;
    }
}
