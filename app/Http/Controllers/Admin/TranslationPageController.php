<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateToEnglishJob;
use App\Models\Translation;
use App\Models\TranslationKey;
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

        // Filter by locale - only show keys that have translations in the selected locale
        if ($locale !== 'all') {
            $query->whereHas('translations', function ($q) use ($locale) {
                $q->where('locale', $locale);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($tq) use ($search) {
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

    public function update(Request $request, Translation $translation)
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

    public function translateSingle(Request $request, TranslationKey $translationKey)
    {
        // Check if English translation already exists
        $existingEnglishTranslation = $translationKey->translations()
            ->where('locale', 'en')
            ->first();

        if ($existingEnglishTranslation) {
            return response()->json([
                'success' => false,
                'message' => 'English translation already exists',
            ], 400);
        }

        // Check if French translation exists
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

    public function translateBatch(Request $request)
    {
        $mode = $request->get('mode', 'missing'); // 'missing' or 'all'

        $query = TranslationKey::whereHas('translations', function ($q) {
            $q->where('locale', 'fr');
        });

        if ($mode === 'missing') {
            $query->whereDoesntHave('translations', function ($q) {
                $q->where('locale', 'en');
            });
        }

        $translationKeys = $query->get();
        $jobsDispatched = 0;

        foreach ($translationKeys as $translationKey) {
            if ($mode === 'all') {
                // For 'all' mode, delete existing English translation first
                $translationKey->translations()->where('locale', 'en')->delete();
            }

            TranslateToEnglishJob::dispatch($translationKey->id);
            $jobsDispatched++;
        }

        return response()->json([
            'success' => true,
            'message' => "Queued {$jobsDispatched} translation jobs",
            'jobs_dispatched' => $jobsDispatched,
        ]);
    }

    private function getTranslationStats(): array
    {
        $totalKeys = TranslationKey::count();
        $frenchTranslations = Translation::where('locale', 'fr')->count();
        $englishTranslations = Translation::where('locale', 'en')->count();
        $missingEnglish = $frenchTranslations - $englishTranslations;

        return [
            'total_keys' => $totalKeys,
            'french_translations' => $frenchTranslations,
            'english_translations' => $englishTranslations,
            'missing_english' => max(0, $missingEnglish),
        ];
    }
}
