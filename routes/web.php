<?php

use App\Http\Controllers\Admin\Api\CreationController;
use App\Http\Controllers\Admin\Api\CreationDraftController;
use App\Http\Controllers\Admin\Api\CreationDraftFeatureController;
use App\Http\Controllers\Admin\Api\CreationDraftScreenshotController;
use App\Http\Controllers\Admin\Api\PersonController;
use App\Http\Controllers\Admin\Api\PictureController;
use App\Http\Controllers\Admin\CreationPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::name('dashboard.')->prefix('dashboard')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Dashboard');
    })->name('index');

    Route::name('creations.')->prefix('creations')->group(function () {
        Route::get('/', [CreationPageController::class, 'listPage'])
            ->name('index');
        Route::get('/drafts', [CreationPageController::class, 'listDraftPage'])
            ->name('drafts.index');
        Route::get('/edit', [CreationPageController::class, 'editPage'])
            ->name('edit');
    });

    Route::name('api.')->prefix('api')->group(function () {
        Route::apiResource('creation-drafts.draft-features', CreationDraftFeatureController::class)->shallow();
        Route::apiResource('creation-drafts.draft-screenshots', CreationDraftScreenshotController::class)->shallow();
        Route::apiResource('pictures', PictureController::class)->except('update');
        Route::apiResources([
            'creations' => CreationController::class,
            'creation-drafts' => CreationDraftController::class,
            'people' => PersonController::class,
        ]);

        Route::post('creation-drafts/{creation_draft}/attach-person', [CreationDraftController::class, 'attachPerson'])
            ->name('creation-drafts.attach-person');
        Route::post('creation-drafts/{creation_draft}/detach-person', [CreationDraftController::class, 'detachPerson'])
            ->name('creation-drafts.detach-person');
        Route::get('creation-drafts/{creation_draft}/people', [CreationDraftController::class, 'getPeople'])
            ->name('creation-drafts.people');
        Route::get('people/{person}/check-associations', [PersonController::class, 'checkAssociations'])
            ->name('people.check-associations');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/api.php';
