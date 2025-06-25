<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
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
     * Get search filters (tags and technologies)
     */
    public function filters(): JsonResponse
    {
        $data = Cache::remember('search.filters', 3600, function () {
            return [
                'tags' => Tag::select('id', 'name', 'slug')
                    ->orderBy('name')
                    ->get(),
                'technologies' => Technology::orderBy('name')
                    ->get()
                    ->map(function (Technology $tech) {
                        return $this->publicControllersService->formatTechnologyForSSR($tech);
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
        ]);

        $query = trim($validated['q'] ?? '');
        $tagIds = array_filter((array) ($validated['tags'] ?? []), 'is_numeric');
        $technologyIds = array_filter((array) ($validated['technologies'] ?? []), 'is_numeric');

        $tagIds = array_map('intval', $tagIds);
        $technologyIds = array_map('intval', $technologyIds);

        $cacheKey = 'search.'.md5($query.implode(',', $tagIds).implode(',', $technologyIds));

        $results = Cache::remember($cacheKey, 300, function () use ($query, $tagIds, $technologyIds) {
            return $this->performSearch($query, $tagIds, $technologyIds);
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
     * @return array<int, array<string, mixed>>
     */
    private function performSearch(string $query, array $tagIds, array $technologyIds): array
    {
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

        return $creations->map(function ($creation) {
            return $this->publicControllersService->formatCreationForSSRShort($creation);
        })->toArray();
    }
}
