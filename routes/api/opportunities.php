
<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Opportunities\Http\Controllers\{
    OpportunityController,
    PublicOpportunityController,
    PublicOpportunityImportController
};
use App\Domains\Opportunities\Http\Controllers\Admin\PublicOpportunityController as AdminPublicOpportunityController;

/*
|--------------------------------------------------------------------------
| Public Opportunities
|--------------------------------------------------------------------------
*/

Route::middleware('cache.headers')->group(function () {
    Route::controller(PublicOpportunityController::class)
        ->prefix('public-opportunities')
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{publicOpportunity}', 'show');
        });

    Route::get('/opportunities', [OpportunityController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Public Opportunity Import
|--------------------------------------------------------------------------
*/

Route::post(
    '/public-opportunities/import',
    [PublicOpportunityImportController::class, 'store']
)->middleware('internal.token');

/*
|--------------------------------------------------------------------------
| Admin Public Opportunities
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::controller(AdminPublicOpportunityController::class)
            ->prefix('public-opportunities')
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/{publicOpportunity}', 'show');
                Route::put('/{publicOpportunity}', 'update');
                Route::post('/{publicOpportunity}/approve', 'approve');
                Route::post('/{publicOpportunity}/reject', 'reject');
                Route::patch(
                    '/{publicOpportunity}/toggle-published',
                    'togglePublished'
                );
            });
    });
