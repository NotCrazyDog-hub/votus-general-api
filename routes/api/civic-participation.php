<?php

use App\Domains\CivicParticipation\Http\Controllers\{
    CategoryController,
    ProposalCommentController,
    ProposalController,
    SantinhoController,
    SuggestionController,
    SuggestionQuestionController
};

use App\Domains\CivicParticipation\Http\Controllers\Admin as Admin;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Civic Participation
|--------------------------------------------------------------------------
| Participação cidadã: propostas, comentários, votos, categorias,
| santinhos e sugestões.
*/

/*
|--------------------------------------------------------------------------
| Public Civic Participation
|--------------------------------------------------------------------------
*/

Route::middleware('cache.headers')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Proposals
    |--------------------------------------------------------------------------
    */

    Route::controller(ProposalController::class)
        ->prefix('proposals')
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');

            Route::post('/', 'store')
                ->middleware('throttle:10,1')
                ->name('proposals.store');

            Route::post('/{id}/vote', 'vote')
                ->middleware('throttle:20,1')
                ->name('proposals.vote');

            Route::delete('/{id}/vote', 'deleteVote')
                ->middleware('throttle:20,1')
                ->name('proposals.vote.delete');
        });

    /*
    |--------------------------------------------------------------------------
    | Proposal Comments
    |--------------------------------------------------------------------------
    */

    Route::controller(ProposalCommentController::class)
        ->prefix('proposals/{id}/comments')
        ->group(function () {
            Route::get('/', 'index');

            Route::post('/', 'store')
                ->middleware('throttle:15,1')
                ->name('proposals.comments.store');

            Route::delete('/{commentId}', 'destroy')
                ->middleware('throttle:15,1')
                ->name('proposals.comments.destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    Route::get('/categories', [CategoryController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Santinho Generation
|--------------------------------------------------------------------------
*/

Route::post('/santinhos', [SantinhoController::class, 'store'])
    ->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| Suggestions
|--------------------------------------------------------------------------
*/

Route::post('/suggestions', [SuggestionController::class, 'store'])
    ->middleware('throttle:10,1');

Route::get('/suggestion-questions', [
    SuggestionQuestionController::class,
    'index',
]);

/*
|--------------------------------------------------------------------------
| Admin Civic Participation
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Proposals Management
        |--------------------------------------------------------------------------
        */

        Route::controller(Admin\ProposalController::class)
            ->prefix('proposals')
            ->group(function () {
                Route::get('/', 'index');
                Route::delete('/{id}', 'destroy');
                Route::delete('/{id}/permanent', 'forceDestroy');
                Route::patch('/{id}/restore', 'restore');
                Route::get('/{id}/comments', 'comments');
                Route::delete(
                    '/{id}/comments/{commentId}',
                    'destroyComment'
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Suggestions Management
        |--------------------------------------------------------------------------
        */

        Route::controller(Admin\SuggestionController::class)
            ->prefix('suggestions')
            ->group(function () {
                Route::get('/', 'index');

                Route::post('/', 'store')
                    ->middleware('throttle:20,1');

                Route::delete('/{suggestion}', 'destroy');
            });

        /*
        |--------------------------------------------------------------------------
        | Suggestion Questions
        |--------------------------------------------------------------------------
        */

        Route::controller(Admin\SuggestionQuestionController::class)
            ->prefix('suggestion-questions')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::put('/{suggestionQuestion}', 'update');
                Route::delete('/{suggestionQuestion}', 'destroy');
            });
    });