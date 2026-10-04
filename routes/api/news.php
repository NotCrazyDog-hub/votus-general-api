<?php

use App\Domains\News\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Domains\News\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| News - público e interno
|--------------------------------------------------------------------------
*/
Route::middleware('cache.headers')->group(function () {
    Route::controller(NewsController::class)->prefix('news')->group(function () {
        Route::get('/', 'index');
        Route::get('/{news}', 'show');
        Route::post('/', 'store')->middleware('internal.token');
    });
});

/*
|--------------------------------------------------------------------------
| News - administração
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::controller(AdminNewsController::class)->prefix('news')->group(function () {
            Route::get('/', 'index');
            Route::post('/collect', 'collect')->middleware('throttle:6,1');
            Route::post('/drain', 'drain')->middleware('throttle:20,1');
            Route::delete('/{id}', 'destroy');
        });
    });