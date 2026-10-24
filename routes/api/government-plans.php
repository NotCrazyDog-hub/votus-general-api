<?php

use App\Domains\GovernmentPlans\Http\Controllers\PresidentialProposalComparisonController;
use Illuminate\Support\Facades\Route;

/*
| Planos de governo: comparação de propostas dos candidatos presidenciais.
| Dados públicos que só mudam quando o import roda, por isso cache.headers.
*/
Route::middleware('cache.headers')
    ->controller(PresidentialProposalComparisonController::class)
    ->prefix('presidential-proposal-comparison')
    ->group(function () {
        Route::get('/themes', 'themes');
        Route::get('/themes/{theme}', 'show');
    });

// Admin entra aqui quando existir (ex.: disparar import pelo painel):
// Route::prefix('admin/government-plans')->middleware(['auth:sanctum', 'admin'])->group(...);