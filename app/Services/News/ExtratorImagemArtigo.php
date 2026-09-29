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
class ExtratorImagemArtigo
{
    public function extrair(string $urlArtigo): ?string
    {
        try {
            $resposta = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Votus)'])
                ->get($urlArtigo);
        } catch (Throwable) {
            return null;
        }

        if (!$resposta->successful()) {
            return null;
        }

        // Só o <head> interessa; evita rodar regex no HTML inteiro.
        $html = (string) $resposta->body();
        $fimHead = stripos($html, '</head>');
        $head = $fimHead !== false ? substr($html, 0, $fimHead) : substr($html, 0, 200000);

        foreach (['og:image', 'og:image:url', 'twitter:image'] as $propriedade) {
            $url = $this->meta($head, $propriedade);

            if ($url !== null) {
                return html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private function meta(string $head, string $propriedade): ?string
    {
        $prop = preg_quote($propriedade, '/');

        // Aceita property= ou name=, e content antes ou depois do atributo.
        $padroes = [
            '/<meta[^>]+(?:property|name)=["\']' . $prop . '["\'][^>]*content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . $prop . '["\']/i',
        ];

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $head, $m) && trim($m[1]) !== '') {
                return trim($m[1]);
            }
        }

        return null;
    }
}
