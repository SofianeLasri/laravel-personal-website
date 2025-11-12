<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
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
            $creationDraft = CreationDraft::with([
                'shortDescriptionTranslationKey.translations',
                'fullDescriptionTranslationKey.translations',
                'contents.content',
            ])->find($request->input('draft-id'));

            // Load specific relations based on content type
            $this->loadContentSpecificRelations($creationDraft);
        }

        if ($request->has('creation-id')) {
            /** @var Creation $creation */
            $creation = Creation::findOrFail($request->input('creation-id'));
            $creationDraft = CreationDraft::fromCreation($creation);
            $creationDraft->load([
                'shortDescriptionTranslationKey.translations',
                'fullDescriptionTranslationKey.translations',
                'contents.content',
            ]);

            // Load specific relations for the new draft
            $this->loadContentSpecificRelations($creationDraft);
        }

        // Get all pictures and videos for content selection
        $pictures = Picture::with('optimizedPictures')
            ->orderBy('created_at', 'desc')
            ->get();

        $videos = Video::orderBy('created_at', 'desc')->get();

        return Inertia::render('dashboard/creations/EditPage', [
            'creationDraft' => $creationDraft,
            'pictures' => $pictures,
            'videos' => $videos,
        ]);
    }

    /**
     * Load content-specific relations based on the content type
     */
    private function loadContentSpecificRelations(?CreationDraft $draft): void
    {
        if (! $draft || ! $draft->contents) {
            return;
        }

        foreach ($draft->contents as $content) {
            if (! $content->content) {
                continue;
            }

            if ($content->content instanceof ContentMarkdown) {
                $content->content->load('translationKey.translations');
            } elseif ($content->content instanceof ContentVideo) {
                $content->content->load([
                    'video.coverPicture',
                ]);
            } elseif ($content->content instanceof ContentGallery) {
                $content->content->load([
                    'pictures' => function ($query) {
                        $query->orderBy('content_gallery_pictures.order')
                            ->withPivot('caption_translation_key_id');
                    },
                ]);

                // Load caption translations for each picture pivot
                foreach ($content->content->pictures as $picture) {
                    if ($picture->pivot && $picture->pivot->caption_translation_key_id) {
                        $captionKey = TranslationKey::with('translations')
                            ->find($picture->pivot->caption_translation_key_id);
                        if ($captionKey) {
                            $picture->pivot->caption_translation_key = $captionKey;
                        }
                    }
                }
            }
        }
    }
}
