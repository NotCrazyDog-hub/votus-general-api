<?php

namespace Tests\Unit\GovernmentPlans;

use App\Domains\GovernmentPlans\Services\LulaSiteProposalParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LulaSiteProposalParserTest extends TestCase
{
    private function html(): string
    {
        return file_get_contents(__DIR__ . '/../../Fixtures/lula-propostas.html');
    }

    public function test_it_reads_every_table_row_and_only_the_cards_without_a_counterpart(): void
    {
        $items = collect((new LulaSiteProposalParser)->parse($this->html()));

        // Cópia do site em 27/10/2026: 44 linhas de tabela em 9 temas...
        $tables = $items->where('block', 'tabela');
        $this->assertCount(44, $tables);
        $this->assertSame(9, $tables->pluck('section')->unique()->count());

        // ...e 3 cartões que só existem no destaque (os outros 24 repetem a tabela).
        $extras = $items->where('block', 'destaque')->pluck('title')->sort()->values()->all();
        $expected = ['Caneta emagrecedora no SUS', 'Emprego bom e bem pago', 'IA, robótica e programação nas escolas'];
        sort($expected);
        $this->assertSame($expected, $extras);

        $this->assertCount(47, $items);
    }

    public function test_titles_and_texts_are_copied_literally(): void
    {
        $items = collect((new LulaSiteProposalParser)->parse($this->html()));

        $library = $items->firstWhere('title', 'Fábrica de bibliotecas');

        $this->assertNotNull($library);
        $this->assertSame('cultura', $library['theme_slug']);
        $this->assertStringStartsWith(
            'Bibliotecas nos condomínios do Minha Casa Minha Vida e pelo menos uma biblioteca pública',
            $library['text'],
        );
        $this->assertSame(
            'Aumento do salário mínimo sempre acima da inflação',
            $items->firstWhere('theme_slug', 'custo-de-vida')['title'],
        );
        // Nada vazio, nada com marcação HTML.
        foreach ($items as $item) {
            $this->assertNotSame('', $item['title']);
            $this->assertNotSame('', $item['text']);
            $this->assertStringNotContainsString('<', $item['title'] . $item['text']);
        }
    }

    public function test_site_sections_map_to_the_existing_votus_themes(): void
    {
        $slugs = collect((new LulaSiteProposalParser)->parse($this->html()))->pluck('theme_slug')->unique()->sort()->values()->all();
        $expected = ['cultura', 'cuidado-familiar', 'custo-de-vida', 'educacao', 'mulheres', 'saude', 'seguranca', 'soberania', 'trabalho-e-renda'];
        sort($expected);

        $this->assertSame($expected, $slugs);
    }

    public function test_a_changed_layout_fails_instead_of_guessing(): void
    {
        $this->expectException(RuntimeException::class);

        (new LulaSiteProposalParser)->parse('<html><body><main><h1>Outra página</h1></main></body></html>');
    }

    public function test_an_unknown_section_fails_instead_of_guessing_the_theme(): void
    {
        $html = str_replace('<span class="tema__nome">Cultura</span>', '<span class="tema__nome">Tema novo</span>', $this->html());

        $this->expectExceptionMessage('Seção desconhecida no site do Lula');

        (new LulaSiteProposalParser)->parse($html);
    }
}
