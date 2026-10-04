<?php

use Illuminate\Support\Facades\Route;

// Public Controllers
use App\Http\Controllers\{
    AiAssistantController,
    CategoryController,
    ProposalCommentController,
    ProposalController,
    SantinhoController,
    SchedulerController,
    SiteVisitController,
    SuggestionController,
    SuggestionQuestionController,
    CourseOfferingController,
    OpportunityController,
    PublicOpportunityController,
    PublicOpportunityImportController,
    UniversityController
};

// Admin Controllers
use App\Http\Controllers\Admin as Admin;

/*
|--------------------------------------------------------------------------
| Legislators & Candidates
|--------------------------------------------------------------------------
| cache.headers só adiciona Cache-Control em respostas GET 200 (a própria
| middleware ignora POST/DELETE) — dados públicos que não mudam a cada
| segundo, então o navegador pode reaproveitar por um tempo curto em vez
| de bater no banco de novo a cada navegação de volta pra mesma tela.
*/
Route::middleware('cache.headers')->group(function () {
    /*
    |----------------------------------------------------------------------
    | Content & Publications (News, Proposals, Categories, Explanations)
    |----------------------------------------------------------------------
    */

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

    Route::get('/universities/{university}', [UniversityController::class, 'show']);

    Route::controller(CourseOfferingController::class)->prefix('course-offerings')->group(function () {
        Route::get('/options/municipalities', 'municipalities');
        Route::get('/options/courses', 'courses');
        Route::get('/', 'index');
        Route::get('/{courseOffering}', 'show');
    });

    Route::controller(PublicOpportunityController::class)->prefix('public-opportunities')->group(function () {
        Route::get('/', 'index');
        Route::get('/{publicOpportunity}', 'show');
    });

    Route::get('/opportunities', [OpportunityController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Interactive Features, Telemetry & AI
|--------------------------------------------------------------------------
*/
Route::post('/ai-assistant/ask', [AiAssistantController::class, 'ask'])
    ->middleware('throttle:10,1')
    ->name('ai-assistant.ask');

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
        Route::post('/coletar-noticias', 'runNewsPipeline');
        Route::post('/processar-fila-noticias', 'processNewsQueue');
    });
});

Route::post('/public-opportunities/import', [PublicOpportunityImportController::class, 'store'])
    ->middleware('internal.token');

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

        // Proposals Management
        Route::controller(Admin\ProposalController::class)->prefix('proposals')->group(function () {
            Route::get('/', 'index');
            Route::delete('/{id}', 'destroy');
            Route::delete('/{id}/permanent', 'forceDestroy');
            Route::patch('/{id}/restore', 'restore');
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

        // Public Opportunities Management
        Route::controller(Admin\PublicOpportunityController::class)->prefix('public-opportunities')->group(function () {
            Route::get('/', 'index');
            Route::get('/{publicOpportunity}', 'show');
            Route::put('/{publicOpportunity}', 'update');
            Route::post('/{publicOpportunity}/approve', 'approve');
            Route::post('/{publicOpportunity}/reject', 'reject');
            Route::patch('/{publicOpportunity}/toggle-published', 'togglePublished');
        });
    });
});

require __DIR__.'/api/legislatures.php';
require __DIR__.'/api/elections.php';
require __DIR__.'/api/executives.php';
require __DIR__.'/api/news.php';
require __DIR__.'/api/explanations.php';