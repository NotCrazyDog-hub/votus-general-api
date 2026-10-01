<?php

namespace Database\Seeders;

use App\Models\NewsSource;
use Illuminate\Database\Seeder;

class NewsSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $feeds = [
            'ultimasnoticias' => 'https://agenciabrasil.ebc.com.br/rss/ultimasnoticias/feed.xml',
            'politica' => 'https://agenciabrasil.ebc.com.br/rss/politica/feed.xml',
            'economia' => 'https://agenciabrasil.ebc.com.br/rss/economia/feed.xml',
            'educacao' => 'https://agenciabrasil.ebc.com.br/rss/educacao/feed.xml',
            'justica' => 'https://agenciabrasil.ebc.com.br/rss/justica/feed.xml',
            'saude' => 'https://agenciabrasil.ebc.com.br/rss/saude/feed.xml',
            'direitos-humanos' => 'https://agenciabrasil.ebc.com.br/rss/direitos-humanos/feed.xml',
            'internacional' => 'https://agenciabrasil.ebc.com.br/rss/internacional/feed.xml',
            'geral' => 'https://agenciabrasil.ebc.com.br/rss/geral/feed.xml',
        ];

        $agenciaBrasil = NewsSource::firstOrCreate(
            ['slug' => 'agencia-brasil'],
            [
                'nome' => 'Agência Brasil',
                'tipo_coleta' => 'rss',
                'url_base' => 'https://agenciabrasil.ebc.com.br',
                'feeds' => $feeds,
                'ativa' => true,
                'offset_minutos' => 15,
                'limite_falhas' => 5,
            ]
        );

        // Reaplica a lista de feeds em fontes já existentes (ex: categorias novas
        // adicionadas depois), sem mexer no estado do circuit breaker (ativa/falhas).
        $agenciaBrasil->update(['feeds' => $feeds]);

        $poder360Feeds = [
            'geral' => 'https://www.poder360.com.br/feed/',
        ];

        $poder360 = NewsSource::firstOrCreate(
            ['slug' => 'poder360'],
            [
                'nome' => 'Poder360',
                'tipo_coleta' => 'rss',
                'url_base' => 'https://www.poder360.com.br',
                'feeds' => $poder360Feeds,
                'ativa' => true,
                'offset_minutos' => 15,
                'limite_falhas' => 5,
            ]
        );

        $poder360->update(['feeds' => $poder360Feeds]);
    }
}
