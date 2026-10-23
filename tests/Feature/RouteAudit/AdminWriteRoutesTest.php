<?php

namespace Tests\Feature\RouteAudit;

use App\Domains\CivicParticipation\Enums\ProposalStatus;
use App\Domains\CivicParticipation\Models\Proposal;
use App\Domains\CivicParticipation\Models\ProposalComment;
use App\Domains\CivicParticipation\Models\ProposalVote;
use App\Domains\CivicParticipation\Models\Suggestion;
use App\Domains\CivicParticipation\Models\SuggestionQuestion;
use App\Domains\Explanations\Models\TrustedSource;
use App\Domains\News\Models\News;
use App\Domains\Opportunities\Models\PublicOpportunity;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class AdminWriteRoutesTest extends RouteAuditTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->makeUser(admin: true));
    }

    private function proposal(string $title, ProposalStatus $status = ProposalStatus::Published): Proposal
    {
        $proposal = Proposal::create(['title' => $title, 'content' => 'Conteúdo de teste', 'author' => 'Teste', 'status' => $status]);
        ProposalVote::create(['proposal_id' => $proposal->id, 'visitor_id' => "v-{$title}", 'vote_type' => 'legal']);
        ProposalComment::create(['proposal_id' => $proposal->id, 'visitor_id' => "v-{$title}", 'author_name' => 'A', 'content' => 'Comentário']);

        return $proposal;
    }

    public function test_removing_a_proposal_is_reversible_and_touches_only_that_proposal(): void
    {
        $target = $this->proposal('alvo');
        $other = $this->proposal('outra');

        $this->deleteJson("/api/admin/proposals/{$target->id}")->assertOk();

        $this->assertSame(ProposalStatus::Removed, $target->fresh()->status); // linha continua no banco
        $this->assertSame(ProposalStatus::Published, $other->fresh()->status);
        $this->assertSame(2, ProposalVote::count());

        $this->patchJson("/api/admin/proposals/{$target->id}/restore")->assertOk();
        $this->assertSame(ProposalStatus::Published, $target->fresh()->status);

        // Restaurar algo que não está removido não faz nada.
        $this->patchJson("/api/admin/proposals/{$other->id}/restore")->assertNotFound();
        $this->deleteJson('/api/admin/proposals/999999')->assertNotFound();
    }

    public function test_permanent_delete_cascades_only_to_the_target_proposal(): void
    {
        $target = $this->proposal('alvo');
        $other = $this->proposal('outra');

        $this->deleteJson("/api/admin/proposals/{$target->id}/permanent")->assertOk();

        $this->assertNull(Proposal::find($target->id));
        $this->assertSame(0, ProposalVote::where('proposal_id', $target->id)->count());
        $this->assertSame(0, ProposalComment::where('proposal_id', $target->id)->count());
        $this->assertNotNull(Proposal::find($other->id));
        $this->assertSame(1, ProposalVote::where('proposal_id', $other->id)->count());
        $this->assertSame(1, ProposalComment::where('proposal_id', $other->id)->count());

        $this->deleteJson("/api/admin/proposals/{$target->id}/permanent")->assertNotFound();
    }

    public function test_admin_comment_delete_requires_the_comment_to_belong_to_the_proposal(): void
    {
        $a = $this->proposal('a');
        $b = $this->proposal('b');
        $commentOfB = ProposalComment::where('proposal_id', $b->id)->first();

        $this->deleteJson("/api/admin/proposals/{$a->id}/comments/{$commentOfB->id}")->assertNotFound();
        $this->assertNotNull($commentOfB->fresh());

        $this->deleteJson("/api/admin/proposals/{$b->id}/comments/{$commentOfB->id}")->assertOk();
        $this->assertNull($commentOfB->fresh());
        $this->assertSame(1, ProposalComment::count());
    }

    public function test_news_delete_removes_only_that_news(): void
    {
        $news = fn (string $slug) => News::create([
            'title' => "Notícia {$slug}", 'ai_summary' => 'Resumo', 'url' => "https://exemplo.test/{$slug}",
            'published_at' => now(), 'relevance_score' => 5, 'keywords' => ['teste'],
        ]);
        $target = $news('a');
        $other = $news('b');

        $this->deleteJson("/api/admin/news/{$target->id}")->assertOk();

        $this->assertNull(News::find($target->id));
        $this->assertNotNull(News::find($other->id));
        $this->deleteJson("/api/admin/news/{$target->id}")->assertNotFound();
    }

    public function test_admin_suggestions_create_validate_and_delete(): void
    {
        $this->postJson('/api/admin/suggestions', ['message' => ''])->assertStatus(422);
        $this->assertSame(0, Suggestion::count());

        $id = $this->postJson('/api/admin/suggestions', ['message' => 'Sugestão de teste', 'email' => 'a@teste.local'])
            ->assertCreated()->json('data.id');
        $other = Suggestion::create(['message' => 'Outra']);

        $this->deleteJson("/api/admin/suggestions/{$id}")->assertOk();
        $this->assertNull(Suggestion::find($id));
        $this->assertNotNull($other->fresh());
        $this->deleteJson("/api/admin/suggestions/{$id}")->assertNotFound();
    }

    public function test_suggestion_questions_crud_and_the_answer_cascade(): void
    {
        $this->postJson('/api/admin/suggestion-questions', ['text' => 'Escolha', 'type' => 'choice'])
            ->assertStatus(422); // escolha sem opções

        $id = $this->postJson('/api/admin/suggestion-questions', ['text' => 'Qual tema?', 'type' => 'choice', 'options' => ['A', 'B']])
            ->assertCreated()->json('data.id');

        $this->putJson("/api/admin/suggestion-questions/{$id}", ['text' => 'Qual tema? (editado)', 'type' => 'text'])
            ->assertOk()->assertJsonPath('data.text', 'Qual tema? (editado)');

        // Uma resposta de visitante ligada à pergunta.
        $suggestion = Suggestion::create(['message' => null]);
        DB::table('suggestion_answers')->insert(['suggestion_id' => $suggestion->id, 'suggestion_question_id' => $id, 'answer' => 'resposta', 'created_at' => now(), 'updated_at' => now()]);
        $keep = SuggestionQuestion::create(['text' => 'Outra', 'type' => 'text', 'required' => false, 'order_index' => 2]);

        $this->deleteJson("/api/admin/suggestion-questions/{$id}")->assertOk();

        $this->assertNull(SuggestionQuestion::find($id));
        $this->assertNotNull($keep->fresh());
        // Comportamento documentado na migration: as respostas da pergunta
        // apagada também somem (cascadeOnDelete). A sugestão em si fica.
        $this->assertSame(0, DB::table('suggestion_answers')->count());
        $this->assertNotNull($suggestion->fresh());
    }

    public function test_trusted_sources_create_reject_duplicates_update_and_delete(): void
    {
        // Este endpoint devolve o objeto direto (sem envelope "data").
        $id = $this->postJson('/api/admin/trusted-sources', ['name' => 'Agência Gov', 'base_url' => 'https://www.agencia.gov.br/noticias/x'])
            ->assertCreated()->json('id');
        $this->assertSame('agencia.gov.br', TrustedSource::find($id)->domain);

        $this->postJson('/api/admin/trusted-sources', ['name' => 'Repetida', 'base_url' => 'https://agencia.gov.br'])
            ->assertStatus(422);
        $this->assertSame(1, TrustedSource::count());

        $this->putJson("/api/admin/trusted-sources/{$id}", ['name' => 'Agência Gov (editada)', 'domain' => 'agencia.gov.br', 'is_active' => false])
            ->assertOk();
        $this->assertFalse((bool) TrustedSource::find($id)->is_active);

        $this->deleteJson("/api/admin/trusted-sources/{$id}")->assertOk();
        $this->assertNull(TrustedSource::find($id));
    }

    // Rotas endereçadas pelo source_key (PublicOpportunity::getRouteKeyName),
    // igual ao painel do front (adminService.ts).
    public function test_public_opportunity_review_actions_only_change_the_review_status(): void
    {
        $opportunity = fn (string $key) => PublicOpportunity::create([
            'source_key' => $key, 'type' => 'concurso', 'title' => "Concurso {$key}", 'review_status' => 'pending',
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        $target = $opportunity('a');
        $other = $opportunity('b');

        $this->postJson("/api/admin/public-opportunities/{$target->source_key}/approve")->assertOk();
        $this->assertSame('approved', $target->fresh()->review_status);

        $this->patchJson("/api/admin/public-opportunities/{$target->source_key}/toggle-published")->assertOk();
        $this->assertSame('pending', $target->fresh()->review_status);

        $this->postJson("/api/admin/public-opportunities/{$target->source_key}/reject")->assertOk();
        $this->assertSame('rejected', $target->fresh()->review_status);
        $this->assertNotNull($target->fresh()); // rejeitar não apaga (evita o n8n recriar)

        $this->putJson("/api/admin/public-opportunities/{$target->source_key}", ['type' => 'concurso', 'registration_start' => '2026-11-10', 'registration_end' => '2026-11-01'])
            ->assertStatus(422); // fim antes do início

        $this->assertSame('pending', $other->fresh()->review_status);
        $this->postJson('/api/admin/public-opportunities/nao-existe/approve')->assertNotFound();
    }
}
