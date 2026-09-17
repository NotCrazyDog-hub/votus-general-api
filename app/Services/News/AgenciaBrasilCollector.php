<?php

namespace App\Services\News;

use App\Services\News\Concerns\ExtraiTextoDeHtml;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class AgenciaBrasilCollector
{
    use ExtraiTextoDeHtml;

    /**
     * Busca e interpreta o feed RSS de uma categoria da Agência Brasil.
     *
     * @return array<int, array{title:string,url:string,conteudo_original:string,original_summary:string,published_at:?string,image_url:?string,category:?string}>
     */
    public function coletar(string $feedUrl): array
    {
        $resposta = Http::timeout(20)->get($feedUrl);

        if ($resposta->failed()) {
            throw new RuntimeException("Falha ao buscar feed {$feedUrl}: HTTP {$resposta->status()}");
        }

        $corpo = trim($resposta->body());

        if ($corpo === '') {
            throw new RuntimeException("Feed vazio em {$feedUrl}.");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($corpo);

        if ($xml === false || !isset($xml->channel->item)) {
            libxml_clear_errors();
            throw new RuntimeException("XML inválido ou sem itens em {$feedUrl}.");
        }

        $itens = [];

        foreach ($xml->channel->item as $item) {
            $link = trim((string) $item->link);

            if ($link === '') {
                continue;
            }

            $categoriaPrincipal = isset($item->category[0]) ? trim((string) $item->category[0]) : null;
            $imagem = trim((string) ($item->{'imagem-destaque'} ?? ''));

            $conteudoOriginal = trim((string) $item->description);

            $itens[] = [
                'title' => trim((string) $item->title),
                'url' => $link,
                'conteudo_original' => $conteudoOriginal,
                'original_summary' => $this->textoLimpo($conteudoOriginal),
                'published_at' => $this->interpretarData((string) $item->pubDate),
                'image_url' => $imagem !== '' ? $imagem : null,
                'category' => $categoriaPrincipal !== '' ? $categoriaPrincipal : null,
            ];
        }

        return $itens;
    }

    private function interpretarData(string $pubDate): ?string
    {
        if (trim($pubDate) === '') {
            return null;
        }

        try {
            return Carbon::parse($pubDate)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
