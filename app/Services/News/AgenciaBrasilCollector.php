<?php

namespace App\Services\News;

use App\Services\News\Concerns\ExtractsTextFromHtml;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class AgenciaBrasilCollector
{
    use ExtractsTextFromHtml;

    /**
     * Busca e interpreta o feed RSS de uma categoria da Agência Brasil.
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
            $image = trim((string) ($item->{'imagem-destaque'} ?? ''));

            $originalContent = trim((string) $item->description);

            $items[] = [
                'title' => trim((string) $item->title),
                'url' => $link,
                'conteudo_original' => $originalContent,
                'original_summary' => $this->cleanText($originalContent),
                'published_at' => $this->parseDate((string) $item->pubDate),
                'image_url' => $image !== '' ? $image : null,
                'category' => $mainCategory !== '' ? $mainCategory : null,
            ];
        }

        return $items;
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
