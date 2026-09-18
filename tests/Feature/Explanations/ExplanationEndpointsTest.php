<?php

namespace Tests\Feature\Explanations;

use App\Models\Explanation;
use App\Models\TrustedSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExplanationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function criarExplicacaoCompleta(string $status): Explanation
    {
        $explanation = Explanation::create([
            'title' => 'Câmara dos Deputados',
            'slug' => 'camara-dos-deputados-' . uniqid(),
            'question_title' => 'Você sabe o que é a Câmara?',
            'category' => 'Órgãos e instituições',
            'summary' => 'Resumo.',
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        $explanation->sources()->create([
            'source_name' => 'Fonte X',
            'source_url' => 'https://fontex.com',
            'source_domain' => 'fontex.com',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $question = $explanation->quizQuestions()->create([
                'question' => "Pergunta {$i}?",
                'explanation' => 'Explicação.',
                'position' => $i,
                'based_on_content_version' => 1,
            ]);

            for ($j = 1; $j <= 4; $j++) {
                $question->options()->create([
                    'option_text' => "Opção {$j}",
                    'is_correct' => $j === 1,
                    'position' => $j,
                ]);
            }
        }

        return $explanation;
    }

    public function test_public_index_only_lists_published_explanations(): void
    {
        $this->criarExplicacaoCompleta('published');
        $this->criarExplicacaoCompleta('review');

        $response = $this->getJson('/api/explanations')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_public_show_returns_404_for_a_non_published_explanation(): void
    {
        $explanation = $this->criarExplicacaoCompleta('review');

        $this->getJson("/api/explanations/{$explanation->id}")->assertNotFound();
    }

    public function test_public_show_returns_quiz_with_options_for_a_published_explanation(): void
    {
        $explanation = $this->criarExplicacaoCompleta('published');

        $response = $this->getJson("/api/explanations/{$explanation->id}")->assertOk();

        $response->assertJsonCount(5, 'quiz_questions');
        $response->assertJsonCount(4, 'quiz_questions.0.options');
        $this->assertArrayHasKey('is_correct', $response->json('quiz_questions.0.options.0'));
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/explanations')->assertUnauthorized();
    }

    public function test_admin_routes_reject_non_admin_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/explanations')
            ->assertForbidden();
    }

    public function test_admin_can_create_an_explanation_and_it_dispatches_generation(): void
    {
        Queue::fake();

        TrustedSource::create(['name' => 'Fonte X', 'domain' => 'fontex.com', 'is_active' => true]);

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/explanations', [
            'title' => 'Câmara dos Deputados',
            'question_title' => 'Você sabe o que é a Câmara?',
            'category' => 'Órgãos e instituições',
            'source_urls' => ['https://fontex.com/materia-sobre-camara'],
        ]);

        $response->assertCreated();
        $this->assertSame('generating', $response->json('status'));
        $this->assertDatabaseHas('explanations', ['title' => 'Câmara dos Deputados', 'status' => 'generating']);
        $this->assertDatabaseHas('explanation_sources', ['source_url' => 'https://fontex.com/materia-sobre-camara']);

        Queue::assertPushed(\App\Jobs\Explanations\GerarConteudoExplicacaoJob::class);
    }

    public function test_admin_cannot_create_an_explanation_with_a_url_outside_trusted_domains(): void
    {
        Queue::fake();

        TrustedSource::create(['name' => 'Fonte X', 'domain' => 'fontex.com', 'is_active' => true]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/explanations', [
            'title' => 'Câmara dos Deputados',
            'question_title' => 'Você sabe o que é a Câmara?',
            'category' => 'Órgãos e instituições',
            'source_urls' => ['https://site-nao-confiavel.com/materia'],
        ])->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_admin_cannot_create_an_explanation_without_an_active_trusted_source(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/explanations', [
            'title' => 'Tema qualquer',
            'question_title' => 'Pergunta?',
            'category' => 'Cargos políticos',
            'source_urls' => ['https://qualquer-site.com/materia'],
        ])->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_admin_publish_fails_when_quiz_is_incomplete(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $explanation = Explanation::create([
            'title' => 'Incompleta',
            'slug' => 'incompleta',
            'question_title' => 'Pergunta?',
            'category' => 'Eleições e voto',
            'status' => 'review',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/explanations/{$explanation->id}/publish")
            ->assertStatus(422);

        $this->assertSame('review', $explanation->fresh()->status);
    }

    public function test_admin_can_publish_and_unpublish_a_complete_explanation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $explanation = $this->criarExplicacaoCompleta('review');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/explanations/{$explanation->id}/publish")
            ->assertOk();

        $this->assertSame('published', $explanation->fresh()->status);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/explanations/{$explanation->id}/unpublish")
            ->assertOk();

        $this->assertSame('review', $explanation->fresh()->status);
    }

    public function test_admin_destroy_cascades_to_sources_and_quiz(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $explanation = $this->criarExplicacaoCompleta('review');
        $explanationId = $explanation->id;

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/explanations/{$explanationId}")
            ->assertOk();

        $this->assertDatabaseMissing('explanations', ['id' => $explanationId]);
        $this->assertDatabaseMissing('explanation_sources', ['explanation_id' => $explanationId]);
        $this->assertDatabaseMissing('quiz_questions', ['explanation_id' => $explanationId]);
    }
}
