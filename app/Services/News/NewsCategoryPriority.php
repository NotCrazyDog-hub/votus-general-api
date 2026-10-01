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

    public static function isPriority(?string $category): bool
    {
        if (!$category) {
            return false;
        }

        $normalized = self::normalize($category);

        foreach (self::TERMOS_PRIORITARIOS as $term) {
            if (str_contains($normalized, self::normalize($term))) {
                return true;
            }
        }

        return false;
    }

    public static function isCatchAll(?string $category): bool
    {
        if (!$category) {
            return true;
        }

        $normalized = self::normalize($category);

        foreach (self::TERMOS_CATCH_ALL as $term) {
            if (str_contains($normalized, self::normalize($term))) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $value): string
    {
        $withoutAccents = strtr(mb_strtolower($value), [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        return trim($withoutAccents);
    }
}
