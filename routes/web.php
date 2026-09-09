<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PublicOpportunityController;
use App\Http\Controllers\Admin\PublicOpportunityController as AdminPublicOpportunityController;

use App\Http\Controllers\UniversityController;
use App\Http\Controllers\CourseOfferingController;

// Página inicial
Route::get('/', function () {
    return view('welcome');
});

// UNIVERSIDADE

Route::get(
    '/cursos/{courseOffering}',
    [CourseOfferingController::class, 'show']
)->name('course-offerings.show');

Route::get(
    '/universidades',
    [UniversityController::class, 'index']
)->name('universities.index');

Route::get(
    '/universidades/opcoes/municipios',
    [UniversityController::class, 'municipalities']
)->name('universities.options.municipalities');

Route::get(
    '/universidades/opcoes/cursos',
    [UniversityController::class, 'courses']
)->name('universities.options.courses');

Route::get(
    '/universidades/{university}',
    [UniversityController::class, 'show']
)->name('universities.show');


Route::prefix('admin')->name('admin.')->group(function () {

    // CONCURSOS PÚBLICOS

    Route::get(
        '/oportunidades-publicas',
        [AdminPublicOpportunityController::class, 'index']
    )->name('public-opportunities.index');

    Route::get(
        '/oportunidades-publicas/{opportunity}/editar',
        [AdminPublicOpportunityController::class, 'edit']
    )->name('public-opportunities.edit');

    Route::put(
        '/oportunidades-publicas/{opportunity}',
        [AdminPublicOpportunityController::class, 'update']
    )->name('public-opportunities.update');

    Route::post(
        '/oportunidades-publicas/{opportunity}/publicar',
        [AdminPublicOpportunityController::class, 'approve']
    )->name('public-opportunities.approve');

    Route::post(
        '/oportunidades-publicas/{opportunity}/descartar',
        [AdminPublicOpportunityController::class, 'reject']
    )->name('public-opportunities.reject');

    Route::patch(
        '/oportunidades-publicas/{opportunity}/toggle-published',
        [AdminPublicOpportunityController::class, 'togglePublished']
    )->name('public-opportunities.toggle-published');

});

// CONCURSOS PÚBLICOS

Route::get(
    '/oportunidades-publicas',
    [PublicOpportunityController::class, 'index']
)->name('public-opportunities.index');

Route::get(
    '/oportunidades-publicas/{opportunity}',
    [PublicOpportunityController::class, 'show']
)->name('public-opportunities.show');