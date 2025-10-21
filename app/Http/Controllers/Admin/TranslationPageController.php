<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateToEnglishJob;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TranslationPageController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $locale = $request->get('locale', 'all');
        $perPage = $request->get('per_page', 15);

        $query = TranslationKey::with('translations');

        if ($locale !== 'all') {
            $query->whereHas('translations', function ($q) use ($locale) {
                // @phpstan-ignore-next-line
                $q->where('locale', $locale);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($tq) use ($search) {
                        // @phpstan-ignore-next-line
                        $tq->where('text', 'like', "%{$search}%");
                    });
            });
        }

        $translationKeys = $query->orderBy('key')->paginate($perPage);

        return Inertia::render('dashboard/Translations', [
            'translationKeys' => $translationKeys,
            'filters' => [
                'search' => $search,
                'locale' => $locale,
                'per_page' => $perPage,
            ],
            'stats' => $this->getTranslationStats(),
        ]);
    }

    public function update(Request $request, Translation $translation): JsonResponse
    {
        $request->validate([
            'text' => 'required|string',
        ]);

        $translation->update([
            'text' => $request->text,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Translation updated successfully',
        ]);
    }

    public function translateSingle(TranslationKey $translationKey): JsonResponse
    {
        $existingEnglishTranslation = $translationKey->translations()
            ->where('locale', 'en')
            ->first();

        if ($existingEnglishTranslation && ! empty($existingEnglishTranslation->text)) {
            return response()->json([
                'success' => false,
                'message' => 'English translation already exists',
            ], 400);
        }

        $frenchTranslation = $translationKey->translations()
            ->where('locale', 'fr')
            ->first();

        if (! $frenchTranslation) {
            return response()->json([
                'success' => false,
                'message' => 'No French translation found to translate from',
            ], 400);
        }

        TranslateToEnglishJob::dispatch($translationKey->id);

        return response()->json([
            'success' => true,
            'message' => 'Translation job queued successfully',
        ]);
    }

    public function retranslateSingle(TranslationKey $translationKey): JsonResponse
    {
        $frenchTranslation = $translationKey->translations()
            ->where('locale', 'fr')
            ->first();

        if (! $frenchTranslation) {
            return response()->json([
                'success' => false,
                'message' => 'No French translation found to translate from',
            ], 400);
        }

        // Pass 'overwrite' flag to indicate this is a retranslation
        TranslateToEnglishJob::dispatch($translationKey->id, true);

        return response()->json([
            'success' => true,
            'message' => 'Retranslation job queued successfully',
        ]);
    }

    public function translateBatch(Request $request): JsonResponse
    {
        $mode = $request->get('mode', 'missing'); // 'missing', 'all', or 'retranslate'

        $query = TranslationKey::whereHas('translations', function ($q) {
            // @phpstan-ignore-next-line
            $q->where('locale', 'fr');
        });

        if ($mode === 'missing') {
            $query->where(function ($q) {
                // Include keys without English translation OR with empty English translation
                $q->whereDoesntHave('translations', function ($subQ) {
                    // @phpstan-ignore-next-line
                    $subQ->where('locale', 'en');
                })->orWhereHas('translations', function ($subQ) {
                    // @phpstan-ignore-next-line
                    $subQ->where('locale', 'en')
                        ->where(function ($emptyQ) {
                            // @phpstan-ignore-next-line
                            $emptyQ->whereNull('text')
                                ->orWhere('text', '');
                        });
                });
            });
        } elseif ($mode === 'retranslate') {
            // For retranslate mode, get keys that have both French and English translations
            $query->whereHas('translations', function ($q) {
                // @phpstan-ignore-next-line
                $q->where('locale', 'en');
            });
        }

        $translationKeys = $query->get();
        $jobsDispatched = 0;

        foreach ($translationKeys as $translationKey) {
            if ($mode === 'all') {
                // Legacy mode: delete before retranslating all
                $translationKey->translations()->where('locale', 'en')->delete();
                TranslateToEnglishJob::dispatch($translationKey->id);
            } elseif ($mode === 'retranslate') {
                // New mode: retranslate without deleting
                TranslateToEnglishJob::dispatch($translationKey->id, true);
            } else {
                // Missing mode: translate only missing
                TranslateToEnglishJob::dispatch($translationKey->id);
            }
            $jobsDispatched++;
        }

        return response()->json([
            'success' => true,
            'message' => "Queued {$jobsDispatched} translation jobs",
            'jobs_dispatched' => $jobsDispatched,
        ]);
    }

    /**
     * Get translation statistics
     *
     * @return array<string, int>
     */
    private function getTranslationStats(): array
    {
        $totalKeys = TranslationKey::count();
        $frenchTranslations = Translation::where('locale', 'fr')->count();
        $englishTranslations = Translation::where('locale', 'en')
            ->where('text', '!=', '')
            ->whereNotNull('text')
            ->count();
        $missingEnglish = $frenchTranslations - $englishTranslations;

        return [
            'total_keys' => $totalKeys,
            'french_translations' => $frenchTranslations,
            'english_translations' => $englishTranslations,
            'missing_english' => max(0, $missingEnglish),
        ];
    }
}
