<?php

namespace Tests\Feature\RouteAudit;

use App\Domains\CivicParticipation\Enums\ProposalStatus;
use App\Domains\CivicParticipation\Models\Proposal;
use App\Domains\CivicParticipation\Models\ProposalComment;
use App\Domains\CivicParticipation\Models\ProposalVote;
use App\Domains\CivicParticipation\Models\Suggestion;
use App\Domains\CivicParticipation\Models\SuggestionQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class PublicWriteRoutesTest extends RouteAuditTestCase
{
    public function test_creating_a_proposal_validates_and_publishes_only_one_record(): void
    {
        $this->postJson('/api/proposals', ['title' => 'Sem conteúdo'])->assertStatus(422);
        $this->assertSame(0, Proposal::count());

        $this->postJson('/api/proposals', ['title' => 'Mais ciclovias', 'content' => 'Texto', 'author' => 'Teste'])
            ->assertSuccessful()
            ->assertJsonPath('data.title', 'Mais ciclovias');

        $this->assertSame(1, Proposal::count());
        $this->assertSame(ProposalStatus::Published, Proposal::first()->status);
    }

    public function test_voting_needs_a_visitor_id_keeps_one_vote_per_visitor_and_deletes_only_its_own(): void
    {
        $proposal = Proposal::create(['title' => 'P', 'content' => 'C', 'author' => 'A', 'status' => ProposalStatus::Published]);
        ProposalVote::create(['proposal_id' => $proposal->id, 'visitor_id' => 'outro-visitante', 'vote_type' => 'legal']);

        $this->postJson("/api/proposals/{$proposal->id}/vote", ['vote' => 'legal'])->assertStatus(422);
        $this->postJson("/api/proposals/{$proposal->id}/vote", ['vote' => 'invalido'], ['X-Visitor-Id' => 'v1'])->assertStatus(422);

        $this->postJson("/api/proposals/{$proposal->id}/vote", ['vote' => 'legal'], ['X-Visitor-Id' => 'v1'])->assertSuccessful();
        $this->postJson("/api/proposals/{$proposal->id}/vote", ['vote' => 'legal'], ['X-Visitor-Id' => 'v1'])->assertSuccessful();
        $this->assertSame(1, ProposalVote::where('visitor_id', 'v1')->count()); // sem duplicar

        $this->deleteJson("/api/proposals/{$proposal->id}/vote", [], ['X-Visitor-Id' => 'v1'])->assertSuccessful();
        $this->assertSame(0, ProposalVote::where('visitor_id', 'v1')->count());
        $this->assertSame(1, ProposalVote::where('visitor_id', 'outro-visitante')->count());

        $this->deleteJson("/api/proposals/{$proposal->id}/vote")->assertStatus(422); // sem X-Visitor-Id

        // Proposta removida pelo admin não aceita voto.
        $proposal->update(['status' => ProposalStatus::Removed]);
        $this->postJson("/api/proposals/{$proposal->id}/vote", ['vote' => 'legal'], ['X-Visitor-Id' => 'v1'])->assertNotFound();
    }

    public function test_only_the_author_can_delete_a_comment(): void
    {
        $proposal = Proposal::create(['title' => 'P', 'content' => 'C', 'author' => 'A', 'status' => ProposalStatus::Published]);

        $this->postJson("/api/proposals/{$proposal->id}/comments", ['content' => ''], ['X-Visitor-Id' => 'autor'])->assertStatus(422);
        $id = $this->postJson("/api/proposals/{$proposal->id}/comments", ['content' => 'Concordo'], ['X-Visitor-Id' => 'autor'])
            ->assertSuccessful()->json('data.id');

        // A listagem não expõe o visitor_id (só can_delete).
        $listed = $this->getJson("/api/proposals/{$proposal->id}/comments", ['X-Visitor-Id' => 'outro'])->json('data.0');
        $this->assertArrayNotHasKey('visitor_id', $listed);
        $this->assertFalse($listed['can_delete']);

        $this->deleteJson("/api/proposals/{$proposal->id}/comments/{$id}", [], ['X-Visitor-Id' => 'outro'])->assertForbidden();
        $this->deleteJson("/api/proposals/{$proposal->id}/comments/{$id}")->assertForbidden();
        $this->assertNotNull(ProposalComment::find($id));

        $this->deleteJson("/api/proposals/{$proposal->id}/comments/{$id}", [], ['X-Visitor-Id' => 'autor'])->assertOk();
        $this->assertNull(ProposalComment::find($id));
        $this->deleteJson("/api/proposals/{$proposal->id}/comments/{$id}", [], ['X-Visitor-Id' => 'autor'])->assertNotFound();
    }

    public function test_suggestions_require_the_mandatory_answers(): void
    {
        $required = SuggestionQuestion::create(['text' => 'Obrigatória', 'type' => 'text', 'required' => true, 'order_index' => 1]);
        $optional = SuggestionQuestion::create(['text' => 'Opcional', 'type' => 'text', 'required' => false, 'order_index' => 2]);

        $this->postJson('/api/suggestions', ['answers' => [['suggestion_question_id' => $optional->id, 'answer' => 'x']]])
            ->assertStatus(422);
        $this->postJson('/api/suggestions', ['answers' => [['suggestion_question_id' => 999, 'answer' => 'x']]])
            ->assertStatus(422);
        $this->assertSame(0, Suggestion::count());

        $this->postJson('/api/suggestions', ['answers' => [['suggestion_question_id' => $required->id, 'answer' => 'Minha resposta']]])
            ->assertCreated();
        $this->assertSame(1, Suggestion::count());
        $this->assertSame(1, DB::table('suggestion_answers')->count());
    }

    public function test_telemetry_endpoints_record_one_row_each(): void
    {
        $this->postJson('/api/site-visits')->assertSuccessful();
        $this->postJson('/api/santinhos')->assertSuccessful();

        $this->assertSame(1, DB::table('site_visits')->count());
        $this->assertSame(1, DB::table('santinho_generations')->count());
    }

    public function test_token_protected_internal_endpoints_reject_requests_without_the_token(): void
    {
        config(['services.scheduler.token' => 'token-de-teste']);

        $this->postJson('/api/news', ['title' => 'x'])->assertUnauthorized();
        $this->postJson('/api/public-opportunities/import', [])->assertUnauthorized();
        $this->postJson('/api/schedule/coletar-noticias')->assertUnauthorized();
        $this->postJson('/api/schedule/processar-fila-noticias')->assertUnauthorized();
        $this->postJson('/api/schedule/coletar-noticias', [], ['X-Scheduler-Token' => 'errado'])->assertUnauthorized();
    }

    public function test_the_ai_assistant_validates_and_relays_to_the_configured_webhook(): void
    {
        config(['services.n8n.webhook_url' => 'https://n8n.exemplo.test/webhook']);
        Http::fake(['n8n.exemplo.test/*' => Http::response(['resposta' => 'ok'])]);

        $this->postJson('/api/ai-assistant/ask', [])->assertStatus(422);
        $this->postJson('/api/ai-assistant/ask', ['mensagem' => 'O que faz um senador?'])->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_committee_review_routes_are_back_under_internal_and_need_a_token(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map->uri()->all();

        $this->assertContains('api/internal/committee-topic-matches/pending', $uris);
        $this->assertContains('api/internal/committee-topic-matches/{committeeTopic}/review', $uris);
        $this->assertNotContains('api/committee-topic-matches/pending', $uris);

        $this->getJson('/api/internal/committee-topic-matches/pending')->assertUnauthorized();
        $this->postJson('/api/internal/committee-topic-matches/1/review', ['approved' => true, 'confidence' => 1])->assertUnauthorized();
    }
}
