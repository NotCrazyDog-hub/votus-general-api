<?php

use App\Domains\Explanations\Http\Controllers\Admin\ExplanationController as AdminExplanationController;
use App\Domains\Explanations\Http\Controllers\Admin\TrustedSourceController;
use App\Domains\Explanations\Http\Controllers\ExplanationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Explanations - público
|--------------------------------------------------------------------------
*/
Route::middleware('cache.headers')->group(function () {
    Route::controller(ExplanationController::class)->prefix('explanations')->group(function () {
        Route::get('/', 'index');
        Route::get('/{explanation}', 'show');
    });
});

/*
|--------------------------------------------------------------------------
| Explanations - administração
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::controller(AdminExplanationController::class)->prefix('explanations')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store')->middleware('throttle:6,1');
            Route::post('/drain', 'drain')->middleware('throttle:20,1');
            Route::get('/{explanation}', 'show');
            Route::put('/{explanation}', 'update');
            Route::post('/{explanation}/publish', 'publish');
            Route::patch('/{explanation}/unpublish', 'unpublish');
            Route::delete('/{explanation}', 'destroy');
        });

        Route::controller(TrustedSourceController::class)->prefix('trusted-sources')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{trustedSource}', 'update');
            Route::delete('/{trustedSource}', 'destroy');
        });
    });