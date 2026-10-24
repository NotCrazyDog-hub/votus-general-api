<?php

namespace App\Domains\GovernmentPlans\Services;

use App\Domains\News\Services\Concerns\ExtractsTextFromHtml;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

class GovernmentPlanSiteService
{
    use ExtractsTextFromHtml;

    public function fetchPages(array $source): array
    {
        $url = $source['url'] ?? null;
        $allowedHosts = $source['allowed_hosts'] ?? [];
        $allowedPathPrefixes = $source['allowed_path_prefixes'] ?? [];
        $maxPages = (int) ($source['max_pages'] ?? 1);

        if (!is_string($url) || $url === '' || $allowedHosts === [] || $allowedPathPrefixes === []) {
            throw new RuntimeException('A fonte oficial do plano de governo está configurada de forma inválida.');
        }

        $queue = [$url];
        $visited = [];
        $pages = [];

        while ($queue !== [] && count($pages) < $maxPages) {
            $currentUrl = array_shift($queue);
            $normalizedUrl = $this->normalizeUrl($currentUrl);

            if (isset($visited[$normalizedUrl])) {
                continue;
            }
            $visited[$normalizedUrl] = true;

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; VotusBot/1.0; +https://votusproj.vercel.app)',
            ])->timeout(30)->get($currentUrl);

            if ($response->failed()) {
                if ($pages === []) {
                    throw new RuntimeException("Falha ao buscar {$currentUrl}: HTTP {$response->status()}");
                }

                continue;
            }

            $crawler = new Crawler($response->body(), $currentUrl);
            $text = $this->extractContentText($crawler, $response->body());

            if ($text !== '') {
                $pages[] = ['url' => $currentUrl, 'text' => $text];
            }

            foreach ($this->governmentPlanLinks($crawler, $currentUrl, $allowedHosts, $allowedPathPrefixes) as $link) {
                if (!isset($visited[$this->normalizeUrl($link)])) {
                    $queue[] = $link;
                }
            }
        }

        if ($pages === []) {
            throw new RuntimeException('Não foi possível extrair conteúdo das páginas oficiais do plano de governo.');
        }

        return $pages;
    }

    private function extractContentText(Crawler $crawler, string $html): string
    {
        $content = $crawler->filter('main, article, [role="main"]');

        if ($content->count() > 0) {
            $html = $content->first()->html() ?: $html;
        }

        return $this->cleanText($html);
    }

    private function governmentPlanLinks(Crawler $crawler, string $baseUrl, array $allowedHosts, array $allowedPathPrefixes): array
    {
        $links = [];

        foreach ($crawler->filter('a[href]')->each(fn (Crawler $link) => $link->attr('href')) as $href) {
            if (!is_string($href) || $href === '') {
                continue;
            }

            $url = $this->absoluteUrl($baseUrl, $href);
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $path = '/' . ltrim((string) parse_url($url, PHP_URL_PATH), '/');

            if (in_array($host, $allowedHosts, true)
                && collect($allowedPathPrefixes)->contains(fn (string $prefix) => str_starts_with($path, $prefix))) {
                $links[$this->normalizeUrl($url)] = $url;
            }
        }

        return array_values($links);
    }

    private function absoluteUrl(string $baseUrl, string $href): string
    {
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '/');

        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        return $origin . rtrim(dirname($path), '/') . '/' . $href;
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = rtrim((string) ($parts['path'] ?? '/'), '/') ?: '/';

        return strtolower(($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $path);
    }
}
