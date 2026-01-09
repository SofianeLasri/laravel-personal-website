<?php

namespace App\Services;

use App\Enums\BlogPostType;
use App\Enums\CategoryColor;
use App\Enums\CreationType;
use App\Enums\ExperienceType;
use App\Enums\TechnologyType;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Certification;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\Video;
use App\Services\Formatters\BlogPostFormatter;
use App\Services\Formatters\CreationFormatter;
use App\Services\Formatters\ExperienceFormatter;
use App\Services\Formatters\MediaFormatter;
use App\Services\Formatters\TranslationHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PublicControllersService
{
    private const DEVELOPMENT_TYPES = [
        CreationType::PORTFOLIO,
        CreationType::LIBRARY,
        CreationType::TOOL,
        CreationType::WEBSITE,
        CreationType::OTHER,
    ];

    public function __construct(
        private readonly TranslationHelper $translationHelper,
        private readonly MediaFormatter $mediaFormatter,
        private readonly CreationFormatter $creationFormatter,
        private readonly BlogPostFormatter $blogPostFormatter,
        private readonly ExperienceFormatter $experienceFormatter,
    ) {}

    /**
     * Get the projects count and development years of experience.
     *
     * @return array{yearsOfExperience: 0|float, count: int<0, max>}
     */
    public function getDevelopmentStats(): array
    {
        $stats = [
            'yearsOfExperience' => 0,
        ];

        $baseQuery = Creation::whereIn('type', self::DEVELOPMENT_TYPES)->get();
        $creationCount = $baseQuery->count();

        $stats['count'] = $creationCount;

        // Calculate years of experience based on the earliest professional experience
        $earliestWorkExperience = Experience::where('type', ExperienceType::EMPLOI)
            ->orderBy('started_at')
            ->first();

        if ($earliestWorkExperience) {
            $stats['yearsOfExperience'] = round(now()->diffInYears($earliestWorkExperience->started_at, true));
        }

        return $stats;
    }

    /**
     * Get all the Laravel projects.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>
     * }>
     */
    public function getLaravelCreations(): Collection
    {
        $laravel = Technology::where('name', 'Laravel')->first();

        if (! $laravel) {
            return collect();
        }

        $developmentCreations = Creation::whereIn('type', self::DEVELOPMENT_TYPES)
            ->whereHas('technologies', function ($query) use ($laravel) {
                $query->where('technologies.id', $laravel->id);
            })
            ->with([
                'logo',
                'coverImage',
                'shortDescriptionTranslationKey.translations',
                'technologies.iconPicture',
                'technologies.descriptionTranslationKey.translations',
            ])
            ->orderByRaw('(ended_at IS NULL) DESC, ended_at DESC')->get();

        return $developmentCreations->map(fn (Creation $creation) => $this->creationFormatter->formatShort($creation));
    }

    /**
     * Get all the projects.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>
     * }>
     */
    public function getCreations(): Collection
    {
        $creations = Creation::with([
            'logo.optimizedPictures',
            'coverImage.optimizedPictures',
            'shortDescriptionTranslationKey.translations',
            'technologies.iconPicture.optimizedPictures',
            'technologies.descriptionTranslationKey.translations',
        ])->orderByRaw('(ended_at IS NULL) DESC, ended_at DESC')->get();

        return $creations->map(fn (Creation $creation) => $this->creationFormatter->formatShort($creation));
    }

    /**
     * Format the Creation model for SSR short view.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>
     * }
     */
    public function formatCreationForSSRShort(Creation $creation): array
    {
        return $this->creationFormatter->formatShort($creation);
    }

    /**
     * Format the Creation model for SSR full view.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string|null,
     *     endedAtFormatted: string|null,
     *     type: CreationType,
     *     shortDescription: string|null,
     *     contents: array<int, array{id: int, order: int, content_type: string, markdown?: string, gallery?: array{id: int, pictures: array<int, mixed>}, video?: array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string, caption: string|null}}>,
     *     externalUrl: string|null,
     *     sourceCodeUrl: string|null,
     *     features: array<int, array{id: int, title: string, description: string, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     screenshots: array<int, array{id: int, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, caption: string, order: int}>,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     people: array<int, array{id: int, name: string, url: string|null, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     videos: array<int, array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string}>,
     *     githubData: array{name: string, description: string|null, stars: int, forks: int, watchers: int, language: string|null, topics: array<string>, license: string|null, updated_at: string, created_at: string, open_issues: int, default_branch: string, size: int, url: string, homepage: string|null}|null,
     *     githubLanguages: array<string, float>|null,
     *     packagistData: array{name: string, description: string|null, downloads: int, daily_downloads: int, monthly_downloads: int, stars: int, dependents: int, suggesters: int, type: string|null, repository: string|null, github_stars: int|null, github_watchers: int|null, github_forks: int|null, github_open_issues: int|null, language: string|null, license: array<string>|null, latest_version: string|null, latest_stable_version: string|null, created_at: string|null, updated_at: string|null, url: string, maintainers: array<array{name: string, avatar_url: string|null}>, php_version: string|null, laravel_version: string|null}|null
     * }
     */
    public function formatCreationForSSRFull(Creation $creation): array
    {
        return $this->creationFormatter->formatFull($creation);
    }

    /**
     * Get all the technology experiences.
     *
     * @return Collection<int, array{
     *     id: int,
     *     technologyId: int,
     *     name: string,
     *     description: string,
     *     creationCount: int,
     *     type: TechnologyType,
     *     typeLabel: string,
     *     iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}
     * }>
     */
    public function getTechnologyExperiences(): Collection
    {
        $experiences = TechnologyExperience::with([
            'technology.iconPicture.optimizedPictures',
            'technology.descriptionTranslationKey.translations',
            'descriptionTranslationKey.translations',
        ])->get();

        return $experiences->map(fn (TechnologyExperience $experience) => $this->experienceFormatter->formatTechnologyExperience($experience));
    }

    /**
     * Get all the experiences.
     *
     * @return Collection<int, array{
     *     id: int,
     *     title: string,
     *     organizationName: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     type: ExperienceType,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string,
     *     endedAtFormatted: string|null
     * }>
     */
    public function getExperiences(): Collection
    {
        $experiences = Experience::with([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
            'logo.optimizedPictures',
            'technologies.iconPicture.optimizedPictures',
            'technologies.descriptionTranslationKey.translations',
        ])->get();

        return $experiences->map(fn (Experience $experience) => $this->experienceFormatter->formatExperience($experience));
    }

    /**
     * Get all certifications for SSR.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     level: string|null,
     *     score: string|null,
     *     date: string,
     *     dateFormatted: string,
     *     link: string|null,
     *     picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null
     * }>
     */
    public function getCertifications(): Collection
    {
        $certifications = Certification::with('picture.optimizedPictures')->orderBy('date', 'desc')->get();

        return $certifications->map(fn (Certification $certification) => $this->experienceFormatter->formatCertification($certification));
    }

    /**
     * Get experiences filtered by type for SSR.
     *
     * @param  ExperienceType  $type  The experience type to filter by
     * @return Collection<int, array{
     *     id: int,
     *     title: string,
     *     organizationName: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     type: ExperienceType,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string,
     *     endedAtFormatted: string|null
     * }>
     */
    public function getExperiencesByType(ExperienceType $type): Collection
    {
        $experiences = Experience::where('type', $type)
            ->with([
                'titleTranslationKey.translations',
                'shortDescriptionTranslationKey.translations',
                'fullDescriptionTranslationKey.translations',
                'logo.optimizedPictures',
                'technologies.iconPicture.optimizedPictures',
                'technologies.descriptionTranslationKey.translations',
            ])
            ->orderBy('started_at', 'desc')
            ->get();

        return $experiences->map(fn (Experience $experience) => $this->experienceFormatter->formatExperience($experience));
    }

    /**
     * Get all data needed for the certifications career page.
     *
     * @return array{
     *     certifications: Collection<int, array{id: int, name: string, level: string|null, score: string|null, date: string, dateFormatted: string, link: string|null, picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}>,
     *     educationExperiences: Collection<int, array{id: int, title: string, organizationName: string, slug: string, logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null, location: string, websiteUrl: string|null, shortDescription: string, fullDescription: string, technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>, type: ExperienceType, startedAt: string, endedAt: string|null, startedAtFormatted: string, endedAtFormatted: string|null}>,
     *     workExperiences: Collection<int, array{id: int, title: string, organizationName: string, slug: string, logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null, location: string, websiteUrl: string|null, shortDescription: string, fullDescription: string, technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>, type: ExperienceType, startedAt: string, endedAt: string|null, startedAtFormatted: string, endedAtFormatted: string|null}>
     * }
     */
    public function getCertificationsCareerData(): array
    {
        return [
            'certifications' => $this->getCertifications(),
            'educationExperiences' => $this->getExperiencesByType(ExperienceType::FORMATION),
            'workExperiences' => $this->getExperiencesByType(ExperienceType::EMPLOI),
        ];
    }

    /**
     * Get blog posts for public home page with latest article first
     *
     * @return Collection<int, BlogPost>
     */
    public function getBlogPostsForPublicHome(): Collection
    {
        return BlogPost::with([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture',
            'contents' => function ($query) {
                $query->where('content_type', ContentMarkdown::class)->orderBy('order');
            },
            'contents.content.translationKey.translations',
        ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Format the BlogPost model for SSR with short excerpt.
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string|null,
     *     excerpt: string
     * }
     */
    public function formatBlogPostForSSRShort(BlogPost $blogPost): array
    {
        return $this->blogPostFormatter->formatShort($blogPost);
    }

    /**
     * Format the BlogPost model for SSR with long excerpt for hero.
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string|null,
     *     excerpt: string
     * }
     */
    public function formatBlogPostForSSRHero(BlogPost $blogPost): array
    {
        return $this->blogPostFormatter->formatHero($blogPost);
    }

    /**
     * Get blog posts for index page with filters and pagination.
     *
     * @param  array{category?: string|array<string>|null, type?: string|null, sort?: string|null, search?: string|null}  $filters
     * @return array{data: array<int, array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     */
    public function getBlogPostsForIndex(array $filters, int $perPage = 12): array
    {
        $query = BlogPost::with([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture.optimizedPictures',
            'contents' => function ($query) {
                $query->where('content_type', ContentMarkdown::class)->orderBy('order');
            },
            'contents.content.translationKey.translations',
        ]);

        // Apply category filter (handle array)
        if (! empty($filters['category'])) {
            $categories = is_array($filters['category']) ? $filters['category'] : [$filters['category']];
            $query->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('slug', $categories);
            });
        }

        // Apply search filter
        if (! empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->whereHas('titleTranslationKey.translations', function (Builder $q) use ($searchTerm) {
                /** @phpstan-ignore argument.type */
                $q->where('text', 'like', '%'.$searchTerm.'%');
            });
        }

        // Apply sorting
        $sort = $filters['sort'] ?? 'newest';
        $locale = $this->translationHelper->getLocale();
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at');
                break;
            case 'alphabetical':
                $query->join('translation_keys', 'blog_posts.title_translation_key_id', '=', 'translation_keys.id')
                    ->join('translations', function ($join) use ($locale) {
                        $join->on('translation_keys.id', '=', 'translations.translation_key_id')
                            ->where('translations.locale', $locale);
                    })
                    ->orderBy('translations.text')
                    ->select('blog_posts.*');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate results
        $paginator = $query->paginate($perPage);

        // Format posts for SSR
        $formattedPosts = $paginator->map(fn ($post) => $this->blogPostFormatter->formatShort($post));

        return [
            'data' => $formattedPosts->toArray(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Get all blog categories for filters.
     *
     * @return array<int, array{id: int, name: string, slug: string, color: CategoryColor}>
     */
    public function getBlogCategories(): array
    {
        $categories = BlogCategory::with('nameTranslationKey.translations')
            ->orderBy('order')
            ->get();

        return $categories->map(function ($category) {
            $name = $category->nameTranslationKey ?
                $this->translationHelper->getWithFallback($category->nameTranslationKey->translations) : '';

            return [
                'id' => $category->id,
                'name' => $name,
                'slug' => $category->slug,
                'color' => $category->color,
            ];
        })->toArray();
    }

    /**
     * Get all blog categories with post counts for filters.
     *
     * @return array<int, array{id: int, name: string, slug: string, color: CategoryColor, postCount: int}>
     */
    public function getBlogCategoriesWithCounts(): array
    {
        $categories = BlogCategory::with(['nameTranslationKey.translations', 'blogPosts'])
            ->orderBy('order')
            ->get();

        return $categories->map(function ($category) {
            $name = $category->nameTranslationKey ?
                $this->translationHelper->getWithFallback($category->nameTranslationKey->translations) : '';

            return [
                'id' => $category->id,
                'name' => $name,
                'slug' => $category->slug,
                'color' => $category->color,
                'postCount' => $category->blogPosts->count(),
            ];
        })->toArray();
    }

    /**
     * Get a blog post by slug with all its content.
     *
     * @return array<string, mixed>|null
     */
    public function getBlogPostBySlug(string $slug): ?array
    {
        $blogPost = BlogPost::with([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture.optimizedPictures',
            'contents' => function ($query) {
                $query->orderBy('order');
            },
            'contents.content' => function ($query) {
                $query->morphWith([
                    ContentMarkdown::class => ['translationKey.translations'],
                    ContentGallery::class => ['pictures.optimizedPictures'],
                    ContentVideo::class => ['video.coverPicture.optimizedPictures', 'captionTranslationKey.translations'],
                ]);
            },
            'gameReview.coverPicture.optimizedPictures',
            'gameReview.prosTranslationKey.translations',
            'gameReview.consTranslationKey.translations',
        ])
            ->where('slug', $slug)
            ->first();

        if (! $blogPost) {
            return null;
        }

        return $this->blogPostFormatter->formatFull($blogPost);
    }

    /**
     * Get a blog post draft for preview with all its content.
     *
     * @return array<string, mixed>
     */
    public function getBlogPostDraftForPreview(BlogPostDraft $draft): array
    {
        // Load all necessary relations
        $draft->load([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture.optimizedPictures',
            'contents' => function ($query) {
                $query->orderBy('order');
            },
            'contents.content' => function ($query) {
                $query->morphWith([
                    ContentMarkdown::class => ['translationKey.translations'],
                    ContentGallery::class => ['pictures.optimizedPictures'],
                    ContentVideo::class => ['video.coverPicture.optimizedPictures', 'captionTranslationKey.translations'],
                ]);
            },
            'gameReviewDraft.coverPicture.optimizedPictures',
            'gameReviewDraft.prosTranslationKey.translations',
            'gameReviewDraft.consTranslationKey.translations',
        ]);

        return $this->blogPostFormatter->formatDraftFull($draft);
    }

    /**
     * Format the Experience model for SSR.
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     organizationName: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     type: ExperienceType,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string,
     *     endedAtFormatted: string|null
     * }
     */
    public function formatExperienceForSSR(Experience $experience): array
    {
        return $this->experienceFormatter->formatExperience($experience);
    }

    /**
     * Format the Certification model for SSR.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     level: string|null,
     *     score: string|null,
     *     date: string,
     *     dateFormatted: string,
     *     link: string|null,
     *     picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null
     * }
     */
    public function formatCertificationForSSR(Certification $certification): array
    {
        return $this->experienceFormatter->formatCertification($certification);
    }

    /**
     * Format the Technology model for SSR.
     *
     * @return array{
     *     id: int,
     *     creationCount: int,
     *     name: string,
     *     description: string,
     *     type: TechnologyType,
     *     iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}
     * }
     */
    public function formatTechnologyForSSR(Technology $technology): array
    {
        return $this->creationFormatter->formatTechnology($technology);
    }

    /**
     * Format the Picture model for SSR.
     *
     * @return array{
     *     filename: string,
     *     width: int|null,
     *     height: int|null,
     *     avif: array{thumbnail: string, small: string, medium: string, large: string, full: string},
     *     webp: array{thumbnail: string, small: string, medium: string, large: string, full: string},
     *     jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}
     * }
     */
    public function formatPictureForSSR(Picture $picture): array
    {
        return $this->mediaFormatter->formatPicture($picture);
    }

    /**
     * Format the Video model for SSR.
     *
     * @return array{
     *     id: int,
     *     bunnyVideoId: string,
     *     name: string,
     *     coverPicture: array{
     *         filename: string,
     *         width: int|null,
     *         height: int|null,
     *         avif: array{thumbnail: string, small: string, medium: string, large: string, full: string},
     *         webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}
     *     },
     *     libraryId: string
     * }
     */
    public function formatVideoForSSR(Video $video): array
    {
        return $this->mediaFormatter->formatVideo($video);
    }

    /**
     * Format a date according to the locale.
     *
     * @param  string|Carbon|null  $date  The date to format
     * @return string|null Formatted date or null
     */
    public function formatDate(Carbon|string|null $date): ?string
    {
        return $this->translationHelper->formatDate($date);
    }
}
