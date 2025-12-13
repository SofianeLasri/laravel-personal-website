<?php

declare(strict_types=1);

namespace App\Services\Conversion\BlogPost;

use App\Enums\BlogPostType;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\BlogPostDraft;
use App\Models\ContentVideo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Service for validating blog post drafts before publication
 */
class BlogPostValidationService
{
    /**
     * Validate that the draft is ready for publication
     *
     * @throws ValidationException
     */
    public function validate(BlogPostDraft $draft): void
    {
        $validator = Validator::make([
            'title_translation_key_id' => $draft->title_translation_key_id,
            'slug' => $draft->slug,
            'type' => $draft->type?->value,
            'category_id' => $draft->category_id,
        ], [
            'title_translation_key_id' => 'required|integer|exists:translation_keys,id',
            'slug' => 'required|string|max:255',
            'type' => ['required', Rule::enum(BlogPostType::class)],
            'category_id' => 'required|integer|exists:blog_categories,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $this->validateVideos($draft);
    }

    /**
     * Validate that all videos in the draft have cover pictures and are ready
     * Auto-publish videos that are ready but private
     *
     * @throws ValidationException
     */
    public function validateVideos(BlogPostDraft $draft): void
    {
        $videoContents = $draft->contents()
            ->where('content_type', ContentVideo::class)
            ->with('content.video')
            ->get();

        $errors = [];

        foreach ($videoContents as $content) {
            $videoContent = $content->content;

            if (! $videoContent instanceof ContentVideo) {
                continue;
            }

            if (! $videoContent->video_id || ! $videoContent->video) {
                $errors[] = "Un contenu vidéo n'a pas de vidéo associée.";

                continue;
            }

            $video = $videoContent->video;
            $videoName = $video->name ?? 'Sans nom';

            if (! $video->cover_picture_id) {
                $errors[] = "La vidéo '{$videoName}' doit avoir une image de couverture avant publication.";
            }

            if ($video->status !== VideoStatus::READY) {
                $statusText = match ($video->status) {
                    VideoStatus::PENDING => 'en attente',
                    VideoStatus::TRANSCODING => 'en cours de transcodage',
                    VideoStatus::ERROR => 'en erreur',
                };
                $errors[] = "La vidéo '{$videoName}' doit être transcodée avant publication (statut actuel: {$statusText}).";

                continue;
            }

            // Auto-publish video if it's ready but private
            if ($video->visibility === VideoVisibility::PRIVATE) {
                $video->update(['visibility' => VideoVisibility::PUBLIC]);
                Log::info('Video auto-published during blog post publication', [
                    'video_id' => $video->id,
                    'video_name' => $video->name,
                    'blog_post_draft_id' => $draft->id,
                ]);
            }
        }

        if (! empty($errors)) {
            $validator = Validator::make([], []);
            foreach ($errors as $error) {
                $validator->errors()->add('videos', $error);
            }
            throw new ValidationException($validator);
        }
    }
}
