<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegislatorController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\AgenteController;
use App\Http\Controllers\CommitteeTopicMatchController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ProposalCommentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SantinhoController;
use App\Http\Controllers\SiteVisitController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\SuggestionQuestionController;
use App\Http\Controllers\ExplanationController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\ProposalController as AdminProposalController;
use App\Http\Controllers\Admin\SuggestionController as AdminSuggestionController;
use App\Http\Controllers\Admin\SuggestionQuestionController as AdminSuggestionQuestionController;
use App\Http\Controllers\Admin\ExplanationController as AdminExplanationController;
use App\Http\Controllers\Admin\TrustedSourceController as AdminTrustedSourceController;


Route::get('/deputies', [LegislatorController::class, 'indexForDeputies']);
Route::get('/deputies/{external_id}', [LegislatorController::class, 'showDeputy']);
Route::get('/senators', [LegislatorController::class, 'indexForSenators']);
Route::get('/senators/{external_id}', [LegislatorController::class, 'showSenator']);
Route::get('/schedule/status', [SchedulerController::class, 'status']);
Route::post('/schedule/coletar-noticias', [SchedulerController::class, 'executarPipelineNoticias'])
    ->middleware('throttle:6,1');
Route::post('/schedule/processar-fila-noticias', [SchedulerController::class, 'processarFilaNoticias'])
    ->middleware('throttle:6,1');

Route::post('/news', [NewsController::class, 'store'])->middleware('internal.token');
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news}', [NewsController::class, 'show']);

Route::post('/agente/perguntar', [AgenteController::class, 'perguntar'])
->middleware('throttle:10,1')
->name('agente.perguntar');

Route::middleware('auth:sanctum')->group(function () { // ou algum guard/token simples pro n8n
    Route::get('/internal/committee-topic-matches/pending', [CommitteeTopicMatchController::class, 'pending']);
    Route::post('/internal/committee-topic-matches/{committeeTopic}/review', [CommitteeTopicMatchController::class, 'review']);
});

Route::get('/proposals', [ProposalController::class, 'index']);
Route::get('/proposals/{id}', [ProposalController::class, 'show']);
Route::post('/proposals', [ProposalController::class, 'store'])
->middleware('throttle:10,1')
->name('proposals.store');
Route::post('/proposals/{id}/vote', [ProposalController::class, 'vote'])
->middleware('throttle:20,1')
->name('proposals.vote');
Route::delete('/proposals/{id}/vote', [ProposalController::class, 'deleteVote'])
->middleware('throttle:20,1')
->name('proposals.vote.delete');

Route::get('/proposals/{id}/comments', [ProposalCommentController::class, 'index']);
Route::post('/proposals/{id}/comments', [ProposalCommentController::class, 'store'])
->middleware('throttle:15,1')
->name('proposals.comments.store');
Route::delete('/proposals/{id}/comments/{commentId}', [ProposalCommentController::class, 'destroy'])
->middleware('throttle:15,1')
->name('proposals.comments.destroy');

Route::get('/categories', [CategoryController::class, 'index']);

Route::post('/santinhos', [SantinhoController::class, 'store'])->middleware('throttle:30,1');
Route::post('/site-visits', [SiteVisitController::class, 'store'])->middleware('throttle:30,1');
Route::post('/suggestions', [SuggestionController::class, 'store'])->middleware('throttle:10,1');
Route::get('/suggestion-questions', [SuggestionQuestionController::class, 'index']);

Route::get('/explanations', [ExplanationController::class, 'index']);
Route::get('/explanations/{explanation}', [ExplanationController::class, 'show']);

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/news', [AdminNewsController::class, 'index']);
        Route::post('/news/collect', [AdminNewsController::class, 'collect'])->middleware('throttle:6,1');
        Route::post('/news/drain', [AdminNewsController::class, 'drain'])->middleware('throttle:20,1');
        Route::delete('/news/{id}', [AdminNewsController::class, 'destroy']);
        Route::get('/proposals', [AdminProposalController::class, 'index']);
        Route::delete('/proposals/{id}', [AdminProposalController::class, 'destroy']);
        Route::get('/proposals/{id}/comments', [AdminProposalController::class, 'comments']);
        Route::delete('/proposals/{id}/comments/{commentId}', [AdminProposalController::class, 'destroyComment']);
        Route::get('/suggestions', [AdminSuggestionController::class, 'index']);
        Route::post('/suggestions', [AdminSuggestionController::class, 'store'])->middleware('throttle:20,1');
        Route::delete('/suggestions/{suggestion}', [AdminSuggestionController::class, 'destroy']);
        Route::get('/suggestion-questions', [AdminSuggestionQuestionController::class, 'index']);
        Route::post('/suggestion-questions', [AdminSuggestionQuestionController::class, 'store']);
        Route::put('/suggestion-questions/{suggestionQuestion}', [AdminSuggestionQuestionController::class, 'update']);
        Route::delete('/suggestion-questions/{suggestionQuestion}', [AdminSuggestionQuestionController::class, 'destroy']);

        Route::get('/explanations', [AdminExplanationController::class, 'index']);
        Route::post('/explanations', [AdminExplanationController::class, 'store'])->middleware('throttle:6,1');
        Route::post('/explanations/drain', [AdminExplanationController::class, 'drain'])->middleware('throttle:20,1');
        Route::get('/explanations/{explanation}', [AdminExplanationController::class, 'show']);
        Route::put('/explanations/{explanation}', [AdminExplanationController::class, 'update']);
        Route::post('/explanations/{explanation}/publish', [AdminExplanationController::class, 'publish']);
        Route::patch('/explanations/{explanation}/unpublish', [AdminExplanationController::class, 'unpublish']);
        Route::delete('/explanations/{explanation}', [AdminExplanationController::class, 'destroy']);

        Route::get('/trusted-sources', [AdminTrustedSourceController::class, 'index']);
        Route::post('/trusted-sources', [AdminTrustedSourceController::class, 'store']);
        Route::put('/trusted-sources/{trustedSource}', [AdminTrustedSourceController::class, 'update']);
        Route::delete('/trusted-sources/{trustedSource}', [AdminTrustedSourceController::class, 'destroy']);
    });
});
