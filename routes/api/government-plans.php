<?php

use App\Domains\GovernmentPlans\Http\Controllers\PresidentialProposalComparisonController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Government Plans
|--------------------------------------------------------------------------
| Comparação, por tema, das propostas dos planos de governo dos candidatos
| à Presidência (extraídas dos sites oficiais; ver
| import:presidential-government-plans).
*/

Route::middleware('cache.headers')->group(function () {
    Route::controller(PresidentialProposalComparisonController::class)
        ->prefix('presidential-proposal-comparison')
        ->group(function () {
            Route::get('/proposals', 'proposals');
            Route::get('/themes', 'themes');
            Route::get('/themes/{theme}', 'show');
        });
});
