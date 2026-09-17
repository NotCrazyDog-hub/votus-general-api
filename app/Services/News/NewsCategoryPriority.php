<?php

namespace App\Services\News;

class NewsCategoryPriority
{
    /**
     * Termos de editoria/categoria compatíveis com o propósito do Votus
     * (política, eleições, governo, cidadania e políticas públicas). Usado só
     * pra priorizar a ORDEM de avaliação dos itens coletados — a aprovação de
     * fato continua sendo decidida pelo filtro de conteúdo (GroqSummarizerService),
     * já que uma editoria "geral"/"últimas" às vezes traz pauta relevante e uma
     * editoria "boa" pode trazer algo fora do escopo.
     *
     * @var string[]
     */
    private const TERMOS_PRIORITARIOS = [
        'politica', 'política',
        'eleic', 'elei', // eleições, eleitoral
        'justic', 'justiç',
        'educac', 'educaç',
        'saude', 'saúde',
        'economia',
        'direitos-humanos', 'direitos humanos',
        'meio-ambiente', 'meio ambiente',
        'ciencia', 'ciência',
        'governo',
        'seguranca', 'segurança',
    ];

    /**
     * Editorias-catálogo (agregam tudo, sem recorte editorial) que não devem
     * ser tratadas como sinal de relevância por si só.
     *
     * @var string[]
     */
    private const TERMOS_CATCH_ALL = ['geral', 'ultimasnoticias', 'últimas', 'brasil'];

    public static function isPrioritaria(?string $categoria): bool
    {
        if (!$categoria) {
            return false;
        }

        $normalizado = self::normalizar($categoria);

        foreach (self::TERMOS_PRIORITARIOS as $termo) {
            if (str_contains($normalizado, self::normalizar($termo))) {
                return true;
            }
        }

        return false;
    }

    public static function isCatchAll(?string $categoria): bool
    {
        if (!$categoria) {
            return true;
        }

        $normalizado = self::normalizar($categoria);

        foreach (self::TERMOS_CATCH_ALL as $termo) {
            if (str_contains($normalizado, self::normalizar($termo))) {
                return true;
            }
        }

        return false;
    }

    private static function normalizar(string $valor): string
    {
        $semAcento = strtr(mb_strtolower($valor), [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        return trim($semAcento);
    }
}
