<?php

namespace App\Domains\News\Services\Collectors;

use App\Domains\News\Services\Concerns\ExtractsTextFromHtml;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class Poder360Collector
{
    use ExtractsTextFromHtml;

    private const NAMESPACE_CONTENT = 'http://purl.org/rss/1.0/modules/content/';

    /**
     * Busca e interpreta o feed RSS do Poder360. Diferente da Agência
     * Brasil, não há uma tag de imagem dedicada — a foto vem embutida no
     * corpo do artigo (content:encoded), então extraímos o primeiro <img>
     * de lá.
     *
     * @return array<int, array{title:string,url:string,conteudo_original:string,original_summary:string,published_at:?string,image_url:?string,category:?string}>
     */
    public function collect(string $feedUrl): array
    {
        $response = Http::timeout(20)->get($feedUrl);

        if ($response->failed()) {
            throw new RuntimeException("Falha ao buscar feed {$feedUrl}: HTTP {$response->status()}");
        }

        $body = trim($response->body());

        if ($body === '') {
            throw new RuntimeException("Feed vazio em {$feedUrl}.");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);

        if ($xml === false || !isset($xml->channel->item)) {
            libxml_clear_errors();
            throw new RuntimeException("XML inválido ou sem itens em {$feedUrl}.");
        }

        $items = [];

        foreach ($xml->channel->item as $item) {
            $link = trim((string) $item->link);

            if ($link === '') {
                continue;
            }

            $mainCategory = isset($item->category[0]) ? trim((string) $item->category[0]) : null;

            $namespacedContent = $item->children(self::NAMESPACE_CONTENT);
            $htmlContent = trim((string) ($namespacedContent->encoded ?? ''));

            if ($htmlContent === '') {
                $htmlContent = trim((string) $item->description);
            }

            $items[] = [
                'title' => trim((string) $item->title),
                'url' => $link,
                'conteudo_original' => $htmlContent,
                'original_summary' => $this->cleanText($htmlContent),
                'published_at' => $this->parseDate((string) $item->pubDate),
                'image_url' => $this->extractFirstImage($htmlContent),
                'category' => $mainCategory !== '' ? $mainCategory : null,
            ];
        }

        return $items;
    }

    private function extractFirstImage(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    private function parseDate(string $pubDate): ?string
    {
        if (trim($pubDate) === '') {
            return null;
        }

        try {
            // utc(): o feed traz o fuso (ex.: -0300) e o banco guarda em UTC;
            // sem converter, toDateTimeString() descartava o fuso e as datas
            // de fontes com fusos diferentes ficavam desalinhadas em até 3h.
            return Carbon::parse($pubDate)->utc()->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
