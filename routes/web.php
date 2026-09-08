<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ExplanationController;
use App\Http\Controllers\Admin\ExplanationController as AdminExplanationController;
use App\Http\Controllers\Admin\TrustedSourceController;

// Página inicial
Route::get('/', function () {
    return view('welcome');
});


Route::prefix('admin')->name('admin.')->group(function () {

    Route::get(
        '/explicacoes',
        [AdminExplanationController::class, 'index']
    )->name('explanations.index');


    Route::get(
        '/explicacoes/criar',
        [AdminExplanationController::class, 'create']
    )->name('explanations.create');


    Route::post(
        '/explicacoes/gerar',
        [AdminExplanationController::class, 'generate']
    )->name('explanations.generate');


    Route::get(
        '/explicacoes/{explanation}/editar',
        [AdminExplanationController::class, 'edit']
    )->name('explanations.edit');


    Route::put(
        '/explicacoes/{explanation}',
        [AdminExplanationController::class, 'update']
    )->name('explanations.update');


    Route::post(
        '/explicacoes/{explanation}/publicar',
        [AdminExplanationController::class, 'publish']
    )->name('explanations.publish');

    Route::patch(
        '/explicacoes/{explanation}/ocultar',
        [AdminExplanationController::class, 'unpublish']
    )->name('explanations.unpublish');

    Route::delete(
        '/explicacoes/{explanation}',
        [AdminExplanationController::class, 'destroy']
    )->name('explanations.destroy');

    Route::post(
        '/fontes',
        [TrustedSourceController::class, 'store']
    )->name('sources.store');


    Route::put(
        '/fontes/{trustedSource}',
        [TrustedSourceController::class, 'update']
    )->name('sources.update');

    Route::delete(
        '/fontes/{trustedSource}',
        [TrustedSourceController::class, 'destroy']
    )->name('sources.destroy');

});

Route::get(
    '/explicacoes',
    [ExplanationController::class, 'index']
)->name('explanations.index');

Route::get(
    '/explicacoes/{explanation}',
    [ExplanationController::class, 'show']
)->name('explanations.show');