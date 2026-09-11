<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegislatorController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\AgenteController;
use App\Http\Controllers\ProposalController;

Route::get('/deputies', [LegislatorController::class, 'indexForDeputies']);
Route::get('/deputies/{external_id}', [LegislatorController::class, 'showDeputy']);
Route::get('/senators', [LegislatorController::class, 'indexForSenators']);
Route::get('/senators/{external_id}', [LegislatorController::class, 'showSenator']);
Route::get('/schedule/status', [SchedulerController::class, 'status']);

Route::post('/news', [NewsController::class, 'store']);
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news}', [NewsController::class, 'show']);

Route::post('/agente/perguntar', [AgenteController::class, 'perguntar'])
->middleware('throttle:10,1')
->name('agente.perguntar');

Route::get('/proposals', [ProposalController::class, 'index']);
Route::get('/proposals/{id}', [ProposalController::class, 'show']);
Route::post('/proposals/{id}/vote', [ProposalController::class, 'vote'])
->middleware('throttle:20,1')
->name('proposals.vote');
