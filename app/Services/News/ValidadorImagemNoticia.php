<?php

namespace App\Services\News;

use App\Models\News;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Regra do Votus: notícia sem imagem real e utilizável não entra no banco.
 * Chamado ANTES da persistência (ver Concerns\PersisteNoticiasValidas) —
 * nada é salvo para ser "corrigido depois".
 *
 * Nunca inventa nem substitui imagem: só diz se a que veio da fonte serve.
 */
class ValidadorImagemNoticia
{
    public const SEM_IMAGEM = 'sem_imagem';
    public const INVALIDA = 'imagem_invalida';
    public const GENERICA = 'imagem_generica';
    public const INACESSIVEL = 'imagem_inacessivel';

    /**
     * Trechos de nome de arquivo que indicam arte genérica da própria fonte,
     * não foto da matéria — ex.: a Agência Brasil reaproveita
     * "eleicoes-2026-banner.png" e "banner_agenda_-_1170x700.png" em várias
     * notícias diferentes (visto no banco: 5 e 4 notícias com a mesma arte).
     * Também usado no filtro da listagem pública (News::scopeComImagemPublicavel).
     */
    public const TRECHOS_GENERICOS = [
        'banner',
        'placeholder',
        'sem-imagem',
        'sem_imagem',
        'no-image',
        'noimage',
        'default-image',
        'imagem-padrao',
    ];

    /**
     * Uma mesma URL de imagem em notícias diferentes é normal uma ou duas
     * vezes (mesma foto de agência em pautas relacionadas); a partir disso é
     * praticamente certo que é arte de capa/genérica.
     */
    private const MAX_REUSO_DA_MESMA_IMAGEM = 2;

    /**
     * @return string|null null quando a imagem é válida; caso contrário, o
     *                     motivo (uma das constantes acima), usado nos logs.
     */
    public function motivoInvalida(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return self::SEM_IMAGEM;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            return self::INVALIDA;
        }

        if ($this->pareceGenerica($url)) {
            return self::GENERICA;
        }

        if (!$this->responde($url)) {
            return self::INACESSIVEL;
        }

        return null;
    }

    private function pareceGenerica(string $url): bool
    {
        $urlMinuscula = mb_strtolower($url);

        foreach (self::TRECHOS_GENERICOS as $trecho) {
            if (str_contains($urlMinuscula, $trecho)) {
                return true;
            }
        }

        return News::where('image_url', $url)->count() >= self::MAX_REUSO_DA_MESMA_IMAGEM;
    }

    /**
     * A URL precisa responder 2xx com um tipo de imagem — é o que o frontend
     * (next/image) vai buscar. HEAD primeiro (barato); alguns servidores não
     * aceitam HEAD, então cai para um GET limitado ao primeiro byte.
     */
    private function responde(string $url): bool
    {
        try {
            $resposta = Http::timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0 (Votus)'])->head($url);

            if (in_array($resposta->status(), [403, 405, 501], true)) {
                $resposta = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Votus)', 'Range' => 'bytes=0-0'])
                    ->get($url);
            }

            $tipo = mb_strtolower((string) $resposta->header('Content-Type'));

            return $resposta->successful() && str_starts_with($tipo, 'image/');
        } catch (Throwable) {
            return false;
        }
    }
}
