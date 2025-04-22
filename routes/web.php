<?php

use App\Http\Controllers\Admin\Api\CreationController;
use App\Http\Controllers\Admin\Api\CreationDraftController;
use App\Http\Controllers\Admin\Api\CreationDraftFeatureController;
use App\Http\Controllers\Admin\Api\CreationDraftScreenshotController;
use App\Http\Controllers\Admin\Api\ExperienceController;
use App\Http\Controllers\Admin\Api\PersonController;
use App\Http\Controllers\Admin\Api\PictureController;
use App\Http\Controllers\Admin\Api\SocialMediaLinkController;
use App\Http\Controllers\Admin\Api\TagController;
use App\Http\Controllers\Admin\Api\TechnologyController;
use App\Http\Controllers\Admin\Api\TechnologyExperienceController;
use App\Http\Controllers\Admin\CreationPageController;
use App\Http\Controllers\Admin\ExperiencePageController;
use App\Http\Controllers\Admin\SocialMediaLinkPageController;
use App\Http\Controllers\Admin\TechnologyExperiencePageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::name('dashboard.')->prefix('dashboard')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('dashboard/Dashboard');
    })->name('index');

    Route::name('creations.')->prefix('creations')->group(function () {
        Route::get('/', [CreationPageController::class, 'listPage'])
            ->name('index');
        Route::get('/drafts', [CreationPageController::class, 'listDraftPage'])
            ->name('drafts.index');
        Route::get('/edit', [CreationPageController::class, 'editPage'])
            ->name('edit');
    });

    Route::get('/technology-experiences', TechnologyExperiencePageController::class)
        ->name('technology-experiences.index');

    Route::name('experiences.')->prefix('experiences')->group(function () {
        Route::get('/', [ExperiencePageController::class, 'listPage'])
            ->name('index');
        Route::get('/create', [ExperiencePageController::class, 'editPage'])
            ->name('create');
        Route::get('/{id}/edit', [ExperiencePageController::class, 'editPage'])
            ->whereNumber('id')
            ->name('edit');
    });

    Route::get('/social-media-links', SocialMediaLinkPageController::class)
        ->name('social-media-links.index');

    Route::name('api.')->prefix('api')->group(function () {
        Route::apiResource('creation-drafts.draft-features', CreationDraftFeatureController::class)->shallow();
        Route::apiResource('creation-drafts.draft-screenshots', CreationDraftScreenshotController::class)->shallow();
        Route::apiResource('pictures', PictureController::class)->except('update');
        Route::apiResources([
            'creations' => CreationController::class,
            'creation-drafts' => CreationDraftController::class,
            'experiences' => ExperienceController::class,
            'people' => PersonController::class,
            'social-media-links' => SocialMediaLinkController::class,
            'tags' => TagController::class,
            'technologies' => TechnologyController::class,
            'technology-experiences' => TechnologyExperienceController::class,
        ]);

        Route::post('creation-drafts/{creation_draft}/attach-person', [CreationDraftController::class, 'attachPerson'])
            ->name('creation-drafts.attach-person');
        Route::post('creation-drafts/{creation_draft}/detach-person', [CreationDraftController::class, 'detachPerson'])
            ->name('creation-drafts.detach-person');
        Route::get('creation-drafts/{creation_draft}/people', [CreationDraftController::class, 'getPeople'])
            ->name('creation-drafts.people');
        Route::get('people/{person}/check-associations', [PersonController::class, 'checkAssociations'])
            ->name('people.check-associations');

        Route::post('creation-drafts/{creation_draft}/attach-tag', [CreationDraftController::class, 'attachTag'])
            ->name('creation-drafts.attach-tag');
        Route::post('creation-drafts/{creation_draft}/detach-tag', [CreationDraftController::class, 'detachTag'])
            ->name('creation-drafts.detach-tag');
        Route::get('creation-drafts/{creation_draft}/tags', [CreationDraftController::class, 'getTags'])
            ->name('creation-drafts.tags');
        Route::get('tags/{tag}/check-associations', [TagController::class, 'checkAssociations'])
            ->name('tags.check-associations');

        Route::post('creation-drafts/{creation_draft}/attach-technology', [CreationDraftController::class, 'attachTechnology'])
            ->name('creation-drafts.attach-technology');
        Route::post('creation-drafts/{creation_draft}/detach-technology', [CreationDraftController::class, 'detachTechnology'])
            ->name('creation-drafts.detach-technology');
        Route::get('creation-drafts/{creation_draft}/technologies', [CreationDraftController::class, 'getTechnologies'])
            ->name('creation-drafts.technologies');
        Route::get('technologies/{technology}/check-associations', [TechnologyController::class, 'checkAssociations'])
            ->name('technologies.check-associations');

        Route::post('experiences/{experience}/attach-technology', [ExperienceController::class, 'attachTechnology'])
            ->name('experiences.attach-technology');
        Route::post('experiences/{experience}/detach-technology', [ExperienceController::class, 'detachTechnology'])
            ->name('experiences.detach-technology');
        Route::get('experiences/{experience}/technologies', [ExperienceController::class, 'getTechnologies'])
            ->name('experiences.technologies');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/api.php';
