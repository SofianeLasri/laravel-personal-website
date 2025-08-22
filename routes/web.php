<?php

use App\Http\Controllers\Admin\Api\CertificationController;
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
use App\Http\Controllers\Admin\Api\VideoController;
use App\Http\Controllers\Admin\CertificationPageController;
use App\Http\Controllers\Admin\CreationPageController;
use App\Http\Controllers\Admin\DataManagementController;
use App\Http\Controllers\Admin\ExperiencePageController;
use App\Http\Controllers\Admin\PicturePageController;
use App\Http\Controllers\Admin\RequestLogController;
use App\Http\Controllers\Admin\SocialMediaLinkPageController;
use App\Http\Controllers\Admin\TechnologyExperiencePageController;
use App\Http\Controllers\Admin\TranslationPageController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\CertificationsCareerController;
use App\Http\Controllers\Public\ExperienceController as PublicExperienceController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ProjectController;
use App\Http\Controllers\Public\ProjectsController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use Illuminate\Support\Facades\Route;

Route::name('public.')->group(function () {
    Route::get('/cv-pdf', function () {
        return redirect('https://cloud.sl-projects.com/index.php/s/zCdQQKSEoGSSE8X');
    })->name('cv');

    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', AboutController::class)->name('about');
    Route::get('/projects', ProjectsController::class)->name('projects');
    Route::get('/projects/{slug}', ProjectController::class)
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('projects.show');
    Route::get('/certifications-career', CertificationsCareerController::class)
        ->name('certifications-career');
    Route::get('/certifications-career/{slug}', PublicExperienceController::class)
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('experience.show');

    // Search routes
    Route::get('/search', [SearchController::class, 'search'])->name('search');
    Route::get('/search/filters', [SearchController::class, 'filters'])->name('search.filters');

    // Sitemap
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

    // Language preference
    Route::post('/set-language', [\App\Http\Controllers\Public\LanguageController::class, 'setLanguage'])->name('set-language');
});

Route::name('dashboard.')->prefix('dashboard')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\HomeController::class, 'index'])->name('index');
    Route::get('/stats', [\App\Http\Controllers\Admin\HomeController::class, 'stats'])->name('stats');

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

    Route::name('certifications.')->prefix('certifications')->group(function () {
        Route::get('/', [CertificationPageController::class, 'listPage'])
            ->name('index');
        Route::get('/create', [CertificationPageController::class, 'editPage'])
            ->name('create');
        Route::get('/{id}/edit', [CertificationPageController::class, 'editPage'])
            ->whereNumber('id')
            ->name('edit');
    });

    Route::get('/social-media-links', SocialMediaLinkPageController::class)
        ->name('social-media-links.index');

    Route::get('/pictures', PicturePageController::class)
        ->name('pictures.index');

    Route::get('/translations', [TranslationPageController::class, 'index'])
        ->name('translations.index');

    Route::get('/request-logs', [RequestLogController::class, 'index'])
        ->name('request-logs.index');
    Route::post('/request-logs/mark-as-bot', [RequestLogController::class, 'markAsBot'])
        ->name('request-logs.mark-as-bot');

    Route::get('/api-logs', [\App\Http\Controllers\Admin\ApiRequestLogController::class, 'index'])
        ->name('api-logs.index');
    Route::get('/api-logs/{apiRequestLog}', [\App\Http\Controllers\Admin\ApiRequestLogController::class, 'show'])
        ->name('api-logs.show');

    Route::name('data-management.')->prefix('data-management')->group(function () {
        Route::get('/', [DataManagementController::class, 'index'])
            ->name('index');
        Route::post('/export', [DataManagementController::class, 'export'])
            ->name('export');
        Route::get('/export/{requestId}/status', [DataManagementController::class, 'exportStatus'])
            ->name('export-status');
        Route::get('/export/{requestId}/download', [DataManagementController::class, 'downloadExport'])
            ->name('download');
        Route::post('/upload', [DataManagementController::class, 'uploadImportFile'])
            ->name('upload');
        Route::post('/import', [DataManagementController::class, 'import'])
            ->name('import');
        Route::post('/metadata', [DataManagementController::class, 'getImportMetadata'])
            ->name('metadata');
        Route::delete('/cancel', [DataManagementController::class, 'cancelImport'])
            ->name('cancel');
    });

    Route::name('api.')->prefix('api')->group(function () {
        Route::apiResource('creation-drafts.draft-features', CreationDraftFeatureController::class)->shallow();
        Route::apiResource('creation-drafts.draft-screenshots', CreationDraftScreenshotController::class)->shallow();
        Route::apiResource('pictures', PictureController::class)->except('update');
        Route::apiResources([
            'certifications' => CertificationController::class,
            'creations' => CreationController::class,
            'creation-drafts' => CreationDraftController::class,
            'experiences' => ExperienceController::class,
            'people' => PersonController::class,
            'social-media-links' => SocialMediaLinkController::class,
            'tags' => TagController::class,
            'technologies' => TechnologyController::class,
            'technology-experiences' => TechnologyExperienceController::class,
            'videos' => VideoController::class,
        ]);

        // Routes spécifiques pour les vidéos
        Route::get('videos/{video}/metadata', [VideoController::class, 'metadata'])
            ->name('videos.metadata');
        Route::get('videos/{video}/status', [VideoController::class, 'status'])
            ->name('videos.status');
        Route::post('videos/{video}/download-thumbnail', [VideoController::class, 'downloadThumbnail'])
            ->name('videos.download-thumbnail');

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

        Route::post('creation-drafts/{creation_draft}/attach-video', [CreationDraftController::class, 'attachVideo'])
            ->name('creation-drafts.attach-video');
        Route::post('creation-drafts/{creation_draft}/detach-video', [CreationDraftController::class, 'detachVideo'])
            ->name('creation-drafts.detach-video');
        Route::get('creation-drafts/{creation_draft}/videos', [CreationDraftController::class, 'getVideos'])
            ->name('creation-drafts.videos');

        Route::put('translations/{translation}', [TranslationPageController::class, 'update'])
            ->name('translations.update');
        Route::post('translations/{translationKey}/translate', [TranslationPageController::class, 'translateSingle'])
            ->name('translations.translate-single');
        Route::post('translations/translate-batch', [TranslationPageController::class, 'translateBatch'])
            ->name('translations.translate-batch');
    });

    require __DIR__.'/settings.php';
});

require __DIR__.'/auth.php';
