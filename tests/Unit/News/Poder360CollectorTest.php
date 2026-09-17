<?php

namespace Tests\Unit\News;

use App\Services\News\Poder360Collector;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class Poder360CollectorTest extends TestCase
{
    public function test_it_parses_items_and_extracts_the_first_image_from_content_encoded(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/poder360-feed.xml'));

        Http::fake([
            'https://exemplo.com/feed.xml' => Http::response($xml, 200),
        ]);

        $itens = (new Poder360Collector())->coletar('https://exemplo.com/feed.xml');

        $this->assertCount(2, $itens);
        $this->assertSame('Julgamento de caso relevante terá análise preliminar', $itens[0]['title']);
        $this->assertSame('Poder Justiça', $itens[0]['category']);
        $this->assertSame('https://static.poder360.com.br/uploads/2026/09/exemplo.jpg', $itens[0]['image_url']);
        $this->assertStringContainsString('julgamento no STF', $itens[0]['original_summary']);
        $this->assertStringNotContainsString('<p>', $itens[0]['original_summary']);

        // segundo item nao tem imagem no corpo
        $this->assertNull($itens[1]['image_url']);
    }

    public function test_it_throws_on_http_failure(): void
    {
        Http::fake(['https://exemplo.com/feed.xml' => Http::response('', 500)]);

        $this->expectException(RuntimeException::class);

        (new Poder360Collector())->coletar('https://exemplo.com/feed.xml');
    }
}
