<?php

namespace Database\Factories;

use App\Models\Fonte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fonte>
 */
class FonteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Agência Brasil',
            'slug' => 'agencia-brasil',
            'tipo_coleta' => 'rss',
            'url_base' => 'https://agenciabrasil.ebc.com.br',
            'feeds' => [
                'ultimasnoticias' => 'https://exemplo.com/feed.xml',
            ],
            'ativa' => true,
            'offset_minutos' => 15,
            'limite_falhas' => 3,
            'falhas_consecutivas' => 0,
        ];
    }
}
