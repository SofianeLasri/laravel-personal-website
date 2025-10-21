<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creation;
use App\Models\CreationDraft;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreationPageController extends Controller
{
    public function listPage(): Response
    {
        $creations = Creation::all()->load([
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
            'drafts']);

        return Inertia::render('dashboard/creations/List', [
            'creations' => $creations,
        ]);
    }

    public function listDraftPage(): Response
    {
        $creationDrafts = CreationDraft::all()->load(['shortDescriptionTranslationKey.translations', 'fullDescriptionTranslationKey.translations']);

        return Inertia::render('dashboard/creations/ListDrafts', [
            'creationDrafts' => $creationDrafts,
        ]);
    }

    public function editPage(Request $request): Response
    {
        $request->validate([
            'draft-id' => 'sometimes|integer|exists:creation_drafts,id|prohibited_if:creation-id,*',
            'creation-id' => 'sometimes|integer|exists:creations,id|prohibited_if:draft-id,*',
        ]);

        $creationDraft = null;

        if ($request->has('draft-id')) {
            $creationDraft = CreationDraft::find($request->input('draft-id'));
        }

        if ($request->has('creation-id')) {
            /** @var Creation $creation */
            $creation = Creation::findOrFail($request->input('creation-id'));
            $creationDraft = CreationDraft::fromCreation($creation);
        }

        $creationDraft?->load(['shortDescriptionTranslationKey.translations', 'fullDescriptionTranslationKey.translations']);

        return Inertia::render('dashboard/creations/EditPage', [
            'creationDraft' => $creationDraft,
        ]);
    }
}
