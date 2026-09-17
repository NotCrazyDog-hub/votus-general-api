<?php

namespace Database\Factories;

use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'original_summary' => $this->faker->paragraph(),
            'conteudo_original' => $this->faker->paragraphs(3, true),
            'ai_summary' => $this->faker->paragraph(),
            'status_resumo' => 'concluido',
            'url' => $this->faker->unique()->url(),
            'source' => 'Agência Brasil',
            'category' => 'Política',
            'eixo' => 'politica',
            'published_at' => now(),
            'imported_at' => now(),
            'relevance_score' => $this->faker->numberBetween(0, 10),
            'keywords' => $this->faker->words(3),
            'published' => true,
            'image_url' => null,
            'site_logo_url' => null,
        ];
    }
}
