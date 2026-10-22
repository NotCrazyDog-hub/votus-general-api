<?php

namespace Tests\Unit\Radar;

use App\Domains\Radar\Services\CamaraTopicAggregator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CamaraTopicAggregatorTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_it_counts_each_proposition_once_per_state_and_once_per_topic(): void
    {
        $result = $this->aggregate(
            themes: [
                ['100', 'PL', '1', '2025', '48', 'Meio Ambiente'],
                ['100', 'PL', '1', '2025', '56', 'Saúde'],
                ['200', 'PEC', '2', '2025', '56', 'Saúde'],
                ['300', 'PL', '3', '2025', '56', 'Saúde'],
                ['400', 'REQ', '4', '2025', '56', 'Saúde'], // tipo fora do escopo
            ],
            authors: [
                // 100: dois deputados do CE (conta 1 vez no CE) e um de SP
                ['100', '1', '10000', 'CE', '1'],
                ['100', '2', '10000', 'CE', '1'],
                ['100', '3', '10000', 'SP', '1'],
                // 200: proponente do CE + apoio de SP (apoio não conta)
                ['200', '1', '10000', 'CE', '1'],
                ['200', '3', '10000', 'SP', '0'],
                // 300: autor do Senado (outro tipo) e deputado sem UF
                ['300', '', '20000', 'SE', '1'],
                ['300', '9', '10000', '', '1'],
                // 400: tipo fora do escopo
                ['400', '1', '10000', 'CE', '1'],
            ],
        );

        $ce = $result['states']['CE'];
        $this->assertSame(2, $ce['propositions']);
        $this->assertSame(2, $ce['deputies']);
        $this->assertSame(1, $ce['multi_author_same_uf_propositions']);
        $this->assertSame(1, $ce['multi_theme_propositions']);
        $this->assertSame([
            ['code' => '56', 'name' => 'Saúde', 'count' => 2],
            ['code' => '48', 'name' => 'Meio Ambiente', 'count' => 1],
        ], $ce['topics']);

        // SP só tem a 100 (a 200 era apoio)
        $this->assertSame(1, $result['states']['SP']['propositions']);
        $this->assertSame(['CE', 'SP'], array_keys($result['states']));

        $this->assertSame(3, $result['totals']['themed_bills_in_file']);
        $this->assertSame(2, $result['totals']['bills_with_deputy_author']);
        $this->assertSame(1, $result['checks']['author_rows_supporters']);
        $this->assertSame(1, $result['checks']['author_rows_other_types']);
        $this->assertSame(1, $result['checks']['author_rows_without_uf']);
    }

    public function test_duplicated_topic_rows_are_counted_once(): void
    {
        $result = $this->aggregate(
            themes: [
                ['100', 'PL', '1', '2025', '56', 'Saúde'],
                ['100', 'PL', '1', '2025', '56', 'Saúde'],
            ],
            authors: [['100', '1', '10000', 'AM', '1']],
        );

        $this->assertSame(1, $result['checks']['theme_rows_duplicated']);
        $this->assertSame([['code' => '56', 'name' => 'Saúde', 'count' => 1]], $result['states']['AM']['topics']);
    }

    public function test_it_rejects_a_file_from_another_year(): void
    {
        $this->expectException(RuntimeException::class);

        $this->aggregate(
            themes: [['100', 'PL', '1', '2024', '56', 'Saúde']],
            authors: [['100', '1', '10000', 'CE', '1']],
        );
    }

    public function test_it_rejects_an_unexpected_header(): void
    {
        $themes = $this->write("\xEF\xBB\xBF\"uri\";\"siglaTipo\"\n");
        $authors = $this->authorsFile([]);

        $this->expectExceptionMessage('Cabeçalho inesperado');

        (new CamaraTopicAggregator)->aggregate($themes, $authors, 2025, ['PL', 'PEC'], '10000');
    }

    public function test_it_rejects_a_truncated_row(): void
    {
        $themes = $this->themesFile([['100', 'PL', '1', '2025', '56', 'Saúde']]);
        // última linha cortada no meio (download interrompido)
        $authors = $this->authorsFile([['100', '1', '10000', 'CE', '1']]);
        file_put_contents($authors, '"100";"https://x/100";"1"', FILE_APPEND);

        $this->expectExceptionMessage('colunas');

        (new CamaraTopicAggregator)->aggregate($themes, $authors, 2025, ['PL', 'PEC'], '10000');
    }

    private function aggregate(array $themes, array $authors): array
    {
        return (new CamaraTopicAggregator)->aggregate(
            $this->themesFile($themes),
            $this->authorsFile($authors),
            2025,
            ['PL', 'PEC'],
            '10000',
        );
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>  $rows  id, tipo, número, ano, codTema, tema
     */
    private function themesFile(array $rows): string
    {
        $lines = [CamaraTopicAggregator::THEMES_HEADER];
        foreach ($rows as [$id, $type, $number, $year, $code, $name]) {
            $lines[] = ["https://dadosabertos.camara.leg.br/api/v2/proposicoes/{$id}", $type, $number, $year, $code, $name, '0'];
        }

        return $this->write($this->csv($lines));
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>  $rows  id, idDeputado, codTipoAutor, UF, proponente
     */
    private function authorsFile(array $rows): string
    {
        $lines = [CamaraTopicAggregator::AUTHORS_HEADER];
        foreach ($rows as $i => [$id, $deputy, $type, $uf, $proponent]) {
            $lines[] = [$id, "https://x/{$id}", $deputy, '', $type, 'Deputado(a)', 'Nome', 'PART', '', $uf, (string) ($i + 1), $proponent];
        }

        return $this->write($this->csv($lines));
    }

    private function csv(array $lines): string
    {
        return "\xEF\xBB\xBF" . implode("\n", array_map(
            fn ($cols) => '"' . implode('";"', $cols) . '"',
            $lines,
        )) . "\n";
    }

    private function write(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'radar');
        file_put_contents($path, $content);

        return $this->files[] = $path;
    }
}
