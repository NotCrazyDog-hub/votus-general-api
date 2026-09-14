<?php

namespace Tests\Unit\News;

use App\Services\News\AgenciaBrasilCollector;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AgenciaBrasilCollectorTest extends TestCase
{
    public function test_it_parses_valid_feed_items_and_skips_invalid_ones(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/agencia-brasil-feed.xml'));

        Http::fake([
            'https://exemplo.com/feed.xml' => Http::response($xml, 200),
        ]);

        $itens = (new AgenciaBrasilCollector())->coletar('https://exemplo.com/feed.xml');

        $this->assertCount(2, $itens);
        $this->assertSame('Governo anuncia novo pacote de medidas econômicas', $itens[0]['title']);
        $this->assertSame('Economia', $itens[0]['category']);
        $this->assertSame('https://imagens.ebc.com.br/exemplo1.png', $itens[0]['image_url']);
        $this->assertStringContainsString('pacote econômico', $itens[0]['conteudo_original']);
        $this->assertNotNull($itens[0]['published_at']);
    }

    public function test_it_throws_on_http_failure(): void
    {
        Http::fake([
            'https://exemplo.com/feed.xml' => Http::response('', 500),
        ]);

        $this->expectException(RuntimeException::class);

        (new AgenciaBrasilCollector())->coletar('https://exemplo.com/feed.xml');
    }

    public function test_it_throws_on_invalid_xml(): void
    {
        Http::fake([
            'https://exemplo.com/feed.xml' => Http::response('isso não é xml', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new AgenciaBrasilCollector())->coletar('https://exemplo.com/feed.xml');
    }
}
