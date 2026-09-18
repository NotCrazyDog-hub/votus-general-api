<?php

namespace App\Services\Explanations;

use App\Services\News\Concerns\ExtraiTextoDeHtml;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExplanationSourceFetcher
{
    use ExtraiTextoDeHtml;

    // Teto por fonte — evita que uma única página desproporcionalmente
    // grande domine o material de referência mandado à Groq.
    private const MAX_CHARS_POR_FONTE = 4000;

    /**
     * Busca o HTML da URL informada e devolve o texto limpo — mesma lógica
     * de extração já usada nos coletores de notícia (ExtraiTextoDeHtml),
     * só que aqui a fonte é uma URL específica indicada pelo admin, não um
     * feed RSS.
     */
    public function buscarTexto(string $url): string
    {
        // Sem um User-Agent de navegador, vários sites (Wikipedia entre
        // eles) recusam a requisição com HTTP 403 — diferente dos feeds RSS
        // de notícias, que são feitos pra consumo automatizado.
        $resposta = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; VotusBot/1.0; +https://votusproj.vercel.app)',
        ])->timeout(20)->get($url);

        if ($resposta->failed()) {
            throw new RuntimeException("Falha ao buscar {$url}: HTTP {$resposta->status()}");
        }

        $texto = $this->textoLimpo($resposta->body());

        if (trim($texto) === '') {
            throw new RuntimeException("Não foi possível extrair texto de {$url}.");
        }

        return mb_substr($texto, 0, self::MAX_CHARS_POR_FONTE);
    }
}
