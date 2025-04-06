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
        $creations = Creation::all()->load(['shortDescriptionTranslationKey.translations', 'fullDescriptionTranslationKey.translations']);

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
            'draft-id' => 'sometimes|integer|exists:creation_drafts,id',
        ]);
        $creationDraft = CreationDraft::find($request->input('draft-id'));
        $creationDraft?->load(['shortDescriptionTranslationKey.translations', 'fullDescriptionTranslationKey.translations']);

        return Inertia::render('dashboard/creations/EditPage', [
            'creationDraft' => $creationDraft,
        ]);
    }
}
