<?php

use App\Http\Controllers\Admin\Api\BlogCategoryController;
use App\Http\Controllers\Admin\Api\BlogContentGalleryController;
use App\Http\Controllers\Admin\Api\BlogContentMarkdownController;
use App\Http\Controllers\Admin\Api\BlogContentVideoController;
use App\Http\Controllers\Admin\Api\BlogPostController;
use App\Http\Controllers\Admin\Api\BlogPostDraftContentController;
use App\Http\Controllers\Admin\Api\BlogPostDraftController;
use App\Http\Controllers\Admin\Api\CertificationController;
use App\Http\Controllers\Admin\Api\CreationController;
use App\Http\Controllers\Admin\Api\CreationDraftController;
use App\Http\Controllers\Admin\Api\CreationDraftFeatureController;
use App\Http\Controllers\Admin\Api\CreationDraftScreenshotController;
use App\Http\Controllers\Admin\Api\ExperienceController;
use App\Http\Controllers\Admin\Api\GameReviewDraftController;
use App\Http\Controllers\Admin\Api\PersonController;
use App\Http\Controllers\Admin\Api\PictureController;
use App\Http\Controllers\Admin\Api\SocialMediaLinkController;
use App\Http\Controllers\Admin\Api\TagController;
use App\Http\Controllers\Admin\Api\TechnologyController;
use App\Http\Controllers\Admin\Api\TechnologyExperienceController;
use App\Http\Controllers\Admin\Api\VideoController;
use App\Http\Controllers\Admin\ApiRequestLogController;
use App\Http\Controllers\Admin\BlogPostDraftsPageController;
use App\Http\Controllers\Admin\BlogPostEditPageController;
use App\Http\Controllers\Admin\BlogPostsPageController;
use App\Http\Controllers\Admin\CertificationPageController;
use App\Http\Controllers\Admin\CreationPageController;
use App\Http\Controllers\Admin\DataManagementController;
use App\Http\Controllers\Admin\ExperiencePageController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PicturePageController;
use App\Http\Controllers\Admin\RequestLogController;
use App\Http\Controllers\Admin\SocialMediaLinkPageController;
use App\Http\Controllers\Admin\TechnologyExperiencePageController;
use App\Http\Controllers\Admin\TranslationPageController;
use App\Http\Controllers\Admin\VideosPageController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\BlogHomeController;
use App\Http\Controllers\Public\BlogIndexController;
use App\Http\Controllers\Public\CertificationsCareerController;
use App\Http\Controllers\Public\ExperienceController as PublicExperienceController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LanguageController;
use App\Http\Controllers\Public\ProjectController;
use App\Http\Controllers\Public\ProjectsController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use Illuminate\Support\Facades\Route;

// Debug route (only in non-production)
if (config('app.env') !== 'production') {
    Route::get('/debug', [DebugController::class, 'index'])->name('debug');
}

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

    // Blog routes
    Route::get('/blog', [BlogHomeController::class, '__invoke'])->name('blog.home');
    Route::get('/blog/articles', [BlogIndexController::class, '__invoke'])->name('blog.index');

    // Search routes
    Route::get('/search', [SearchController::class, 'search'])->name('search');
    Route::get('/search/filters', [SearchController::class, 'filters'])->name('search.filters');

    // Sitemap
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

    // Language preference
    Route::post('/set-language', [LanguageController::class, 'setLanguage'])->name('set-language');
});

Route::name('dashboard.')->prefix('dashboard')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\HomeController::class, 'index'])->name('index');
    Route::get('/stats', [\App\Http\Controllers\Admin\HomeController::class, 'stats'])->name('stats');

    // Notifications page
    Route::get('/notifications', function () {
        return inertia('dashboard/Notifications');
    })->name('notifications');

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

    Route::get('/videos', VideosPageController::class)
        ->name('videos.index');

    Route::get('/translations', [TranslationPageController::class, 'index'])
        ->name('translations.index');

    Route::get('/request-logs', [RequestLogController::class, 'index'])
        ->name('request-logs.index');
    Route::post('/request-logs/mark-as-bot', [RequestLogController::class, 'markAsBot'])
        ->name('request-logs.mark-as-bot');

    Route::get('/api-logs', [ApiRequestLogController::class, 'index'])
        ->name('api-logs.index');
    Route::get('/api-logs/{apiRequestLog}', [ApiRequestLogController::class, 'show'])
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

    Route::name('blog-posts.')->prefix('blog-posts')->group(function () {
        Route::get('/', [BlogPostsPageController::class, 'listPage'])
            ->name('index');
        Route::get('/drafts', [BlogPostDraftsPageController::class, 'listPage'])
            ->name('drafts.index');
        Route::get('/edit', [BlogPostEditPageController::class, 'editPage'])
            ->name('edit');
    });

    Route::name('api.')->prefix('api')->group(function () {
        // Notifications routes
        Route::get('notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
            ->name('notifications.unread-count');
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('notifications.mark-all-as-read');
        Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])
            ->name('notifications.mark-as-read');
        Route::delete('notifications/clear', [NotificationController::class, 'clearAll'])
            ->name('notifications.clear');
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])
            ->name('notifications.destroy');
        Route::post('notifications', [NotificationController::class, 'store'])
            ->name('notifications.store');
        Route::get('notifications/stream', [NotificationController::class, 'stream'])
            ->name('notifications.stream');

        // Blog categories routes
        Route::apiResource('blog-categories', BlogCategoryController::class);
        Route::post('blog-categories/reorder', [BlogCategoryController::class, 'reorder'])
            ->name('blog-categories.reorder');

        // Blog post drafts routes
        Route::apiResource('blog-post-drafts', BlogPostDraftController::class);

        // Blog post draft content routes
        Route::apiResource('blog-post-draft-contents', BlogPostDraftContentController::class)->except(['index', 'show']);
        Route::post('blog-post-drafts/{blog_post_draft}/contents/reorder', [BlogPostDraftContentController::class, 'reorder'])
            ->name('blog-post-draft-contents.reorder');

        // Blog content routes
        Route::apiResource('blog-content-markdown', BlogContentMarkdownController::class)->except(['index']);
        Route::apiResource('blog-content-gallery', BlogContentGalleryController::class)->except(['index']);
        Route::put('blog-content-galleries/{blog_content_gallery}/pictures', [BlogContentGalleryController::class, 'updatePictures'])
            ->name('blog-content-galleries.update-pictures');
        Route::apiResource('blog-content-video', BlogContentVideoController::class)->except(['index']);

        // Game review draft routes
        Route::apiResource('game-review-drafts', GameReviewDraftController::class)->except(['index', 'create', 'edit']);

        Route::apiResource('creation-drafts.draft-features', CreationDraftFeatureController::class)->shallow();
        Route::apiResource('creation-drafts.draft-screenshots', CreationDraftScreenshotController::class)->shallow();
        Route::apiResource('pictures', PictureController::class)->except('update');
        Route::apiResources([
            'blog-posts' => BlogPostController::class,
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
