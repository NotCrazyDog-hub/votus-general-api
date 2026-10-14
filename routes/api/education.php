
<?php

use App\Domains\Education\Http\Controllers\{
    CourseOfferingController,
    UniversityController
};
use Illuminate\Support\Facades\Route;

Route::middleware('cache.headers')->group(function () {
    Route::get('/universities/{university}', [
        UniversityController::class,
        'show',
    ]);

    Route::controller(CourseOfferingController::class)
        ->prefix('course-offerings')
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{courseOffering}', 'show');
            Route::get('/options/municipalities', 'municipalities');
            Route::get('/options/courses', 'courses');
        });
});
