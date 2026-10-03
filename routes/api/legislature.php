<?php

use App\Domains\Legislatures\Http\Controllers\{
    CommitteeTopicMatchController,
    LegislatorController
};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legislature
|--------------------------------------------------------------------------
| Parlamentares em exercício: Câmara dos Deputados, Senado Federal
| e Assembleia Legislativa do Ceará (ALECE).
*/

Route::middleware('cache.headers')->group(function () {
    Route::controller(LegislatorController::class)->group(function () {
        // Câmara dos Deputados
        Route::get('/deputies', 'indexForDeputies');
        Route::get('/deputies/{external_id}', 'showDeputy');

        // Senado Federal
        Route::get('/senators', 'indexForSenators');
        Route::get('/senators/{external_id}', 'showSenator');

        // Assembleia Legislativa do Ceará
        Route::get('/state-deputies', 'indexForStateDeputies');
        Route::get('/state-deputies/{source_slug}', 'showStateDeputy');
    });
});

/*
|--------------------------------------------------------------------------
| Internal Legislature Tools
|--------------------------------------------------------------------------
| Revisão autenticada dos vínculos entre comissões e temas.
*/

Route::middleware('auth:sanctum')
    ->prefix('internal')
    ->controller(CommitteeTopicMatchController::class)
    ->prefix('committee-topic-matches')
    ->group(function () {
        Route::get('/pending', 'pending');
        Route::post('/{committeeTopic}/review', 'review');
    });