<?php

namespace App\Http\Controllers;

use App\Http\Requests\TranslationRequest;
use App\Http\Resources\TranslationResource;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Support\Facades\Gate;

class TranslationController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Translation::class);

        return TranslationResource::collection(Translation::all());
    }

    public function store(TranslationRequest $request)
    {
        Gate::authorize('create', Translation::class);

        return new TranslationResource(Translation::create($request->validated()));
    }

    public function show(string $key, string $locale)
    {
        $translation = Translation::findByKeyAndLocale($key, $locale);
        if (! $translation) {
            return response()->json(['message' => 'Translation not found'], 404);
        }

        Gate::authorize('view', $translation);

        return new TranslationResource($translation);
    }

    public function update(TranslationRequest $request)
    {
        $translation = null;
        if ($request->has('key')) {
            $translation = Translation::findByKeyAndLocale($request->input('key'), $request->input('locale'));
        } elseif ($request->has('translation_key_id')) {
            $translation = TranslationKey::find($request->input('translation_key_id'))->translations()->where('locale', $request->input('locale'))->first();
        }

        if (! $translation) {
            return response()->json(['message' => 'Translation not found'], 404);
        }

        Gate::authorize('update', $translation);

        $translation->update($request->validated());

        return new TranslationResource($translation);
    }

    public function destroy(Translation $translation)
    {
        Gate::authorize('delete', $translation);

        $translation->delete();

        return response()->json();
    }
}
