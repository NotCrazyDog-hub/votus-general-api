<?php

use App\Domains\Elections\Http\Controllers\CandidateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Elections
|--------------------------------------------------------------------------
| Candidaturas das eleições: Presidência, Governo estadual, Senado,
| Câmara dos Deputados e Assembleias Legislativas.
|
| As URLs foram mantidas para não quebrar o frontend.
*/

Route::middleware('cache.headers')->group(function () {
    Route::controller(CandidateController::class)->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Presidente da República
        |--------------------------------------------------------------------------
        */
        Route::get('/president-candidates', 'indexForPresidents');
        Route::get('/president-candidates/{external_id}', 'showPresident');
        Route::get(
            '/president-candidates/{external_id}/expenses',
            'presidentExpenses',
        );

        /*
        |--------------------------------------------------------------------------
        | Governadores
        |--------------------------------------------------------------------------
        */
        Route::get('/governor-candidates', 'indexForGovernors');
        Route::get('/governor-candidates/{external_id}', 'showGovernor');
        Route::get(
            '/governor-candidates/{external_id}/expenses',
            'governorExpenses',
        );

        /*
        |--------------------------------------------------------------------------
        | Senado Federal
        |--------------------------------------------------------------------------
        */
        Route::get('/senate-candidates', 'indexForSenateCandidates');
        Route::get('/senate-candidates/{external_id}', 'showSenateCandidate');
        Route::get(
            '/senate-candidates/{external_id}/expenses',
            'senateExpenses',
        );

        /*
        |--------------------------------------------------------------------------
        | Câmara dos Deputados
        |--------------------------------------------------------------------------
        */
        Route::get(
            '/federal-deputy-candidates',
            'indexForFederalDeputyCandidates',
        );
        Route::get(
            '/federal-deputy-candidates/{external_id}',
            'showFederalDeputyCandidate',
        );
        Route::get(
            '/federal-deputy-candidates/{external_id}/expenses',
            'federalDeputyExpenses',
        );

        /*
        |--------------------------------------------------------------------------
        | Assembleias Legislativas
        |--------------------------------------------------------------------------
        */
        Route::get(
            '/state-deputy-candidates',
            'indexForStateDeputyCandidates',
        );
        Route::get(
            '/state-deputy-candidates/{external_id}',
            'showStateDeputyCandidate',
        );
        Route::get(
            '/state-deputy-candidates/{external_id}/expenses',
            'stateDeputyExpenses',
        );
    });
});