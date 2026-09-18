<?php

use Illuminate\Support\Facades\Route;

// Public Controllers
use App\Http\Controllers\{
    AgenteController,
    CandidateController,
    CategoryController,
    CommitteeTopicMatchController,
    ExplanationController,
    LegislatorController,
    NewsController,
    ProposalCommentController,
    ProposalController,
    SantinhoController,
    SchedulerController,
    SiteVisitController,
    SuggestionController,
    SuggestionQuestionController
};

// Admin Controllers
use App\Http\Controllers\Admin as Admin;

/*
|--------------------------------------------------------------------------
| Legislators & Candidates
|--------------------------------------------------------------------------
*/
Route::controller(LegislatorController::class)->group(function () {
    Route::get('/deputies', 'indexForDeputies');
    Route::get('/deputies/{external_id}', 'showDeputy');
    Route::get('/senators', 'indexForSenators');
    Route::get('/senators/{external_id}', 'showSenator');
});

Route::controller(CandidateController::class)->group(function () {
    Route::get('/governor-candidates', 'indexForGovernors');
    Route::get('/governor-candidates/{external_id}', 'showGovernor');
    Route::get('/senate-candidates', 'indexForSenateCandidates');
    Route::get('/senate-candidates/{external_id}', 'showSenateCandidate');
    Route::get('/federal-deputy-candidates', 'indexForFederalDeputyCandidates');
    Route::get('/federal-deputy-candidates/{external_id}', 'showFederalDeputyCandidate');
    Route::get('/state-deputy-candidates', 'indexForStateDeputyCandidates');
    Route::get('/state-deputy-candidates/{external_id}', 'showStateDeputyCandidate');
});

/*
|--------------------------------------------------------------------------
| Content & Publications (News, Proposals, Categories, Explanations)
|--------------------------------------------------------------------------
*/
Route::controller(NewsController::class)->prefix('news')->group(function () {
    Route::get('/', 'index');
    Route::get('/{news}', 'show');
    Route::post('/', 'store')->middleware('internal.token');
});

Route::prefix('proposals')->group(function () {
    Route::controller(ProposalController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::post('/', 'store')->middleware('throttle:10,1')->name('proposals.store');
        Route::post('/{id}/vote', 'vote')->middleware('throttle:20,1')->name('proposals.vote');
        Route::delete('/{id}/vote', 'deleteVote')->middleware('throttle:20,1')->name('proposals.vote.delete');
    });

    Route::controller(ProposalCommentController::class)->prefix('{id}/comments')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('throttle:15,1')->name('proposals.comments.store');
        Route::delete('/{commentId}', 'destroy')->middleware('throttle:15,1')->name('proposals.comments.destroy');
    });
});

Route::get('/categories', [CategoryController::class, 'index']);

Route::controller(ExplanationController::class)->prefix('explanations')->group(function () {
    Route::get('/', 'index');
    Route::get('/{explanation}', 'show');
});

/*
|--------------------------------------------------------------------------
| Interactive Features, Telemetry & AI
|--------------------------------------------------------------------------
*/
Route::post('/agente/perguntar', [AgenteController::class, 'perguntar'])
    ->middleware('throttle:10,1')
    ->name('agente.perguntar');

Route::post('/santinhos', [SantinhoController::class, 'store'])->middleware('throttle:30,1');
Route::post('/site-visits', [SiteVisitController::class, 'store'])->middleware('throttle:30,1');
Route::post('/suggestions', [SuggestionController::class, 'store'])->middleware('throttle:10,1');
Route::get('/suggestion-questions', [SuggestionQuestionController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Internal Tools & Scheduler Pipeline
|--------------------------------------------------------------------------
*/
Route::controller(SchedulerController::class)->prefix('schedule')->group(function () {
    Route::get('/status', 'status');
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/coletar-noticias', 'executarPipelineNoticias');
        Route::post('/processar-fila-noticias', 'processarFilaNoticias');
    });
});

Route::middleware('auth:sanctum')->prefix('internal')->group(function () {
    Route::controller(CommitteeTopicMatchController::class)->prefix('committee-topic-matches')->group(function () {
        Route::get('/pending', 'pending');
        Route::post('/{committeeTopic}/review', 'review');
    });
});

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Auth
        Route::post('/logout', [Admin\AuthController::class, 'logout']);
        Route::get('/me', [Admin\AuthController::class, 'me']);
        Route::get('/dashboard', [Admin\DashboardController::class, 'index']);

        // News Management
        Route::controller(Admin\NewsController::class)->prefix('news')->group(function () {
            Route::get('/', 'index');
            Route::post('/collect', 'collect')->middleware('throttle:6,1');
            Route::post('/drain', 'drain')->middleware('throttle:20,1');
            Route::delete('/{id}', 'destroy');
        });

        // Proposals Management
        Route::controller(Admin\ProposalController::class)->prefix('proposals')->group(function () {
            Route::get('/', 'index');
            Route::delete('/{id}', 'destroy');
            Route::get('/{id}/comments', 'comments');
            Route::delete('/{id}/comments/{commentId}', 'destroyComment');
        });

        // Suggestions Management
        Route::controller(Admin\SuggestionController::class)->prefix('suggestions')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store')->middleware('throttle:20,1');
            Route::delete('/{suggestion}', 'destroy');
        });

        // Suggestion Questions
        Route::controller(Admin\SuggestionQuestionController::class)->prefix('suggestion-questions')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{suggestionQuestion}', 'update');
            Route::delete('/{suggestionQuestion}', 'destroy');
        });

        // Explanations Management
        Route::controller(Admin\ExplanationController::class)->prefix('explanations')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store')->middleware('throttle:6,1');
            Route::post('/drain', 'drain')->middleware('throttle:20,1');
            Route::get('/{explanation}', 'show');
            Route::put('/{explanation}', 'update');
            Route::post('/{explanation}/publish', 'publish');
            Route::patch('/{explanation}/unpublish', 'unpublish');
            Route::delete('/{explanation}', 'destroy');
        });

        // Trusted Sources Management
        Route::controller(Admin\TrustedSourceController::class)->prefix('trusted-sources')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{trustedSource}', 'update');
            Route::delete('/{trustedSource}', 'destroy');
        });
    });
});
