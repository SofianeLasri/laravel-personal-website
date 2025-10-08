<?php

namespace App\Http\Controllers\Public;

use App\Enums\BlogPostType;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Creation;
use App\Models\Tag;
use App\Models\Technology;
use App\Services\PublicControllersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    public function __construct(
        private PublicControllersService $publicControllersService
    ) {}

    /**
     * Get search filters (tags, technologies, blog categories, and blog types)
     */
    public function filters(): JsonResponse
    {
        $data = Cache::remember('search.filters', 3600, function () {
            return [
                'tags' => Tag::select('id', 'name', 'slug')
                    ->whereHas('creations')
                    ->orderBy('name')
                    ->get(),
                'technologies' => Technology::whereHas('creations')
                    ->orderBy('name')
                    ->get()
                    ->map(function (Technology $tech) {
                        return $this->publicControllersService->formatTechnologyForSSR($tech);
                    }),
                'blogCategories' => BlogCategory::with('nameTranslationKey.translations')
                    ->whereHas('blogPosts')
                    ->orderBy('order')
                    ->get()
                    ->map(function (BlogCategory $category) {
                        $name = $category->nameTranslationKey ?
                            $this->publicControllersService->getTranslationWithFallback($category->nameTranslationKey->translations) : '';

                        return [
                            'id' => $category->id,
                            'name' => $name,
                            'slug' => $category->slug,
                            'color' => $category->color->value,
                        ];
                    }),
                'blogTypes' => collect(BlogPostType::cases())->map(function (BlogPostType $type) {
                    return [
                        'value' => $type->value,
                        'label' => $type->label(),
                        'icon' => $type->icon(),
                    ];
                }),
            ];
        });

        return response()->json($data);
    }

    /**
     * Perform search
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255|min:1',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'integer|exists:tags,id|min:1',
            'technologies' => 'nullable|array|max:20',
            'technologies.*' => 'integer|exists:technologies,id|min:1',
            'categories' => 'nullable|array|max:20',
            'categories.*' => 'integer|exists:blog_categories,id|min:1',
            'types' => 'nullable|array|max:10',
            'types.*' => 'string|in:'.implode(',', BlogPostType::values()),
        ]);

        $query = trim($validated['q'] ?? '');
        $tagIds = array_filter((array) ($validated['tags'] ?? []), 'is_numeric');
        $technologyIds = array_filter((array) ($validated['technologies'] ?? []), 'is_numeric');
        $categoryIds = array_filter((array) ($validated['categories'] ?? []), 'is_numeric');
        $types = array_filter((array) ($validated['types'] ?? []), 'is_string');

        $tagIds = array_map('intval', $tagIds);
        $technologyIds = array_map('intval', $technologyIds);
        $categoryIds = array_map('intval', $categoryIds);

        $cacheKey = 'search.'.md5($query.implode(',', $tagIds).implode(',', $technologyIds).implode(',', $categoryIds).implode(',', $types));

        $results = Cache::remember($cacheKey, 300, function () use ($query, $tagIds, $technologyIds, $categoryIds, $types) {
            return $this->performSearch($query, $tagIds, $technologyIds, $categoryIds, $types);
        });

        return response()->json([
            'results' => $results,
            'total' => count($results),
        ]);
    }

    /**
     * Perform the actual search logic
     *
     * @param  array<int>  $tagIds
     * @param  array<int>  $technologyIds
     * @param  array<int>  $categoryIds
     * @param  array<string>  $types
     * @return array<int, array<string, mixed>>
     */
    private function performSearch(string $query, array $tagIds, array $technologyIds, array $categoryIds, array $types): array
    {
        $results = [];

        // Search Creations (only if no blog-specific filters are active)
        if (empty($categoryIds) && empty($types)) {
            $creationsQuery = Creation::query()
                ->with([
                    'shortDescriptionTranslationKey.translations',
                    'fullDescriptionTranslationKey.translations',
                    'tags',
                    'technologies',
                    'people',
                    'features.titleTranslationKey.translations',
                ]);

            // Text search
            if (! empty($query)) {
                $creationsQuery->where(function ($q) use ($query) {
                    // Search in creation name
                    $q->where('creations.name', 'like', "%{$query}%")
                        // Search in tags
                        ->orWhereHas('tags', function ($tagQuery) use ($query) {
                            $tagQuery->where('tags.name', 'like', "%{$query}%");
                        })
                        // Search in technologies
                        ->orWhereHas('technologies', function ($techQuery) use ($query) {
                            $techQuery->where('technologies.name', 'like', "%{$query}%");
                        })
                        // Search in people names
                        ->orWhereHas('people', function ($peopleQuery) use ($query) {
                            $peopleQuery->where('people.name', 'like', "%{$query}%");
                        })
                        // Search in feature titles
                        ->orWhereHas('features.titleTranslationKey.translations', function ($featureQuery) use ($query) {
                            $featureQuery->where('translations.text', 'like', "%{$query}%");
                        });
                });
            }

            if (! empty($tagIds)) {
                $creationsQuery->whereHas('tags', function ($tagQuery) use ($tagIds) {
                    $tagQuery->whereIn('tags.id', $tagIds);
                });
            }

            if (! empty($technologyIds)) {
                $creationsQuery->whereHas('technologies', function ($techQuery) use ($technologyIds) {
                    $techQuery->whereIn('technologies.id', $technologyIds);
                });
            }

            $creations = $creationsQuery
                ->orderBy('ended_at', 'desc')
                ->orderBy('started_at', 'desc')
                ->limit(20)
                ->get();

            $results = array_merge($results, $creations->map(function ($creation) {
                $formatted = $this->publicControllersService->formatCreationForSSRShort($creation);
                $formatted['resultType'] = 'creation';

                return $formatted;
            })->toArray());
        }

        // Search Blog Posts (only if no creation-specific filters are active)
        if (empty($tagIds) && empty($technologyIds)) {
            $blogPostsQuery = BlogPost::query()
                ->with([
                    'titleTranslationKey.translations',
                    'category.nameTranslationKey.translations',
                    'coverPicture',
                    'contents' => function ($query) {
                        $query->where('content_type', 'App\Models\BlogContentMarkdown')->orderBy('order');
                    },
                    'contents.content.translationKey.translations',
                ]);

            // Text search
            if (! empty($query)) {
                $blogPostsQuery->where(function ($q) use ($query) {
                    // Search in blog post title
                    $q->whereHas('titleTranslationKey.translations', function ($titleQuery) use ($query) {
                        $titleQuery->where('translations.text', 'like', "%{$query}%");
                    })
                        // Search in category name
                        ->orWhereHas('category.nameTranslationKey.translations', function ($categoryQuery) use ($query) {
                            $categoryQuery->where('translations.text', 'like', "%{$query}%");
                        });
                });
            }

            // Filter by category
            if (! empty($categoryIds)) {
                $blogPostsQuery->whereIn('category_id', $categoryIds);
            }

            // Filter by type
            if (! empty($types)) {
                $blogPostsQuery->whereIn('type', $types);
            }

            $blogPosts = $blogPostsQuery
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $results = array_merge($results, $blogPosts->map(function ($blogPost) {
                $formatted = $this->publicControllersService->formatBlogPostForSSRShort($blogPost);
                $formatted['resultType'] = 'blogPost';

                return $formatted;
            })->toArray());
        }

        // Sort combined results by date (most recent first)
        usort($results, function ($a, $b) {
            $dateA = $a['publishedAt'] ?? $a['endedAt'] ?? $a['startedAt'] ?? null;
            $dateB = $b['publishedAt'] ?? $b['endedAt'] ?? $b['startedAt'] ?? null;

            if (! $dateA && ! $dateB) {
                return 0;
            }
            if (! $dateA) {
                return 1;
            }
            if (! $dateB) {
                return -1;
            }

            return strtotime($dateB) <=> strtotime($dateA);
        });

        // Limit to 20 results
        return array_slice($results, 0, 20);
    }
}
