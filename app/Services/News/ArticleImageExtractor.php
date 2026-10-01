<?php

namespace App\Services\News;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Busca a imagem de capa publicada pela própria matéria (og:image /
 * twitter:image), para itens de feed que não trazem foto. O RSS do Poder360,
 * por exemplo, só inclui <img> em ~1 de cada 10 itens — por isso 40 das 70
 * notícias dele no banco estavam sem imagem —, mas toda página de artigo
 * declara og:image com a foto real da matéria.
 *
 * É a imagem oficial do próprio artigo, não uma substituta: se a página não
 * declarar nenhuma, devolve null e a notícia é descartada pelo validador.
 */
class ArticleImageExtractor
{
    public function extract(string $articleUrl): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Votus)'])
                ->get($articleUrl);
        } catch (Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        // Só o <head> interessa; evita rodar regex no HTML inteiro.
        $html = (string) $response->body();
        $headEnd = stripos($html, '</head>');
        $head = $headEnd !== false ? substr($html, 0, $headEnd) : substr($html, 0, 200000);

        foreach (['og:image', 'og:image:url', 'twitter:image'] as $property) {
            $url = $this->meta($head, $property);

            if ($url !== null) {
                return html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private function meta(string $head, string $property): ?string
    {
        $escapedProperty = preg_quote($property, '/');

        // Aceita property= ou name=, e content antes ou depois do atributo.
        $patterns = [
            '/<meta[^>]+(?:property|name)=["\']' . $escapedProperty . '["\'][^>]*content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . $escapedProperty . '["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $head, $m) && trim($m[1]) !== '') {
                return trim($m[1]);
            }
        }

        return null;
    }
}
