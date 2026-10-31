<?php

namespace App\Domains\GovernmentPlans\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Lê as propostas de https://lula.com.br/propostas/ COPIANDO o texto do site,
 * sem IA: título e texto de cada proposta ficam exatamente como publicados.
 *
 * O site mostra as propostas em dois blocos:
 *  - tabelas por tema ("Proposta" / "O que muda na vida"): a lista completa;
 *  - cartões de destaque por faixa: a maioria repete itens das tabelas com
 *    outro título; alguns existem só no cartão.
 * Importa todas as linhas das tabelas e só os cartões SEM correspondente nas
 * tabelas (mesmo tema, e título ou texto semelhantes = mesma proposta).
 *
 * Se o layout mudar (seção desconhecida, nenhuma tabela, poucas propostas),
 * lança exceção em vez de adivinhar.
 */
class LulaSiteProposalParser
{
    /** Seção do site => [slug do tema no Votus, nome do tema]. */
    private const THEMES = [
        'custo de vida' => ['custo-de-vida', 'Custo de vida'],
        'educacao 4.0' => ['educacao', 'Educação'],
        'educacao' => ['educacao', 'Educação'],
        'saude' => ['saude', 'Saúde'],
        'seguranca' => ['seguranca', 'Segurança'],
        'pela vida das mulheres' => ['mulheres', 'Mulheres'],
        'cuidados' => ['cuidado-familiar', 'Cuidado familiar'],
        'trabalho e empreendedorismo' => ['trabalho-e-renda', 'Trabalho e renda'],
        'trabalho e renda' => ['trabalho-e-renda', 'Trabalho e renda'],
        'cultura' => ['cultura', 'Cultura'],
        'soberania' => ['soberania', 'Soberania'],
    ];

    /** Mínimos para aceitar a leitura (o site tinha 44 linhas de tabela). */
    private const MIN_TABLE_ROWS = 30;

    /**
     * Extrai propostas literais das tabelas e cartões do site, mapeia cada
     * seção para um tema conhecido e elimina cartões que repetem linhas da
     * tabela. Se a estrutura esperada ou a quantidade mínima não for encontrada,
     * lança exceção em vez de importar um conjunto possivelmente incompleto.
     */
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);

        $rows = [];
        $crawler->filter('section.tema')->each(function (Crawler $section) use (&$rows) {
            $name = $this->clean($section->filter('.tema__nome')->count() ? $section->filter('.tema__nome')->text() : '');
            [$slug, $themeName] = $this->theme($name);

            $section->filter('table.tabela tbody tr')->each(function (Crawler $tr) use (&$rows, $slug, $themeName, $section, $name) {
                $title = $this->clean($tr->filter('td.p')->count() ? $tr->filter('td.p')->text() : '');
                $text = $this->clean($tr->filter('td.m')->count() ? $tr->filter('td.m')->text() : '');

                if ($title !== '' && $text !== '') {
                    $rows[] = [
                        'theme_slug' => $slug,
                        'theme_name' => $themeName,
                        'section' => $name,
                        'section_id' => (string) $section->attr('id'),
                        'block' => 'tabela',
                        'title' => $title,
                        'text' => $text,
                    ];
                }
            });
        });

        if (count($rows) < self::MIN_TABLE_ROWS) {
            throw new RuntimeException('Layout do site do Lula mudou: encontradas só ' . count($rows) . ' propostas nas tabelas (esperado ao menos ' . self::MIN_TABLE_ROWS . ').');
        }

        $extras = [];
        $crawler->filter('section.faixa')->each(function (Crawler $faixa) use (&$extras, $rows) {
            $name = $this->clean($faixa->filter('.so-leitor')->count() ? $faixa->filter('.so-leitor')->first()->text() : '');
            [$slug, $themeName] = $this->theme($name);

            $faixa->filter('li.proposta')->each(function (Crawler $li) use (&$extras, $rows, $slug, $themeName, $name) {
                $title = $this->clean($li->filter('.proposta__titulo')->count() ? $li->filter('.proposta__titulo')->text() : '');
                $text = $this->clean($li->filter('.proposta__texto')->count() ? $li->filter('.proposta__texto')->text() : '');

                if ($title === '' || $text === '' || $this->hasCounterpart($title, $text, $slug, $rows)) {
                    return;
                }

                $extras[] = [
                    'theme_slug' => $slug,
                    'theme_name' => $themeName,
                    'section' => $name,
                    'section_id' => '',
                    'block' => 'destaque',
                    'title' => $title,
                    'text' => $text,
                ];
            });
        });

        return array_merge($rows, $extras);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function theme(string $sectionName): array
    {
        $key = Str::of($sectionName)->ascii()->lower()->squish()->toString();

        return self::THEMES[$key]
            ?? throw new RuntimeException("Seção desconhecida no site do Lula: \"{$sectionName}\". Não vou adivinhar o tema.");
    }

    /**
     * O cartão repete uma linha de tabela do MESMO tema quando os títulos têm
     * metade ou mais das palavras em comum, ou título + texto têm 20% ou mais
     * (medido na página de 27/10/2026: os 24 cartões repetidos ficam entre
     * 0,20 e 1,00 e os 3 realmente novos, abaixo de 0,20).
     */
    private function hasCounterpart(string $title, string $text, string $slug, array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['theme_slug'] !== $slug) {
                continue;
            }

            if ($this->overlap($title, $row['title']) >= 0.5
                || $this->overlap("{$title} {$text}", "{$row['title']} {$row['text']}") >= 0.2) {
                return true;
            }
        }

        return false;
    }

    private function overlap(string $a, string $b): float
    {
        $tokens = fn (string $s) => collect(explode(' ', preg_replace('/[^a-z0-9 ]/', ' ', Str::of($s)->ascii()->lower()->toString())))
            ->filter(fn ($w) => strlen($w) > 3)->unique()->values();

        $first = $tokens($a);
        $second = $tokens($b);
        $union = $first->merge($second)->unique()->count();

        return $union === 0 ? 0.0 : $first->intersect($second)->count() / $union;
    }

    private function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
