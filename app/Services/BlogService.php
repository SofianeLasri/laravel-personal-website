<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BlogService
{
    /**
     * Create a new blog post draft
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data): BlogPostDraft
    {
        return BlogPostDraft::create([
            'slug' => $data['slug'],
            'type' => $data['type'],
            'status' => $data['status'] ?? 'draft',
            'category_id' => $data['category_id'],
            'cover_picture_id' => $data['cover_picture_id'] ?? null,
            'blog_post_id' => $data['blog_post_id'] ?? null,
        ]);
    }

    /**
     * Create a draft from an existing blog post
     */
    public function createDraftFromPost(BlogPost $post, bool $copyContent = false): BlogPostDraft
    {
        $draft = $this->createDraft([
            'slug' => $post->slug,
            'type' => $post->type,
            'status' => 'draft',
            'category_id' => $post->category_id,
            'cover_picture_id' => $post->cover_picture_id,
            'blog_post_id' => $post->id,
        ]);

        if ($copyContent) {
            foreach ($post->contents as $content) {
                $draft->contents()->create([
                    'content_type' => $content->content_type,
                    'content_id' => $content->content_id,
                    'order' => $content->order,
                ]);
            }

            if ($post->type === 'game_review' && $post->gameReview) {
                $this->copyGameReviewToDraft($post->gameReview, $draft);
            }
        }

        return $draft->fresh(['contents']);
    }

    /**
     * Copy game review to draft
     */
    private function copyGameReviewToDraft(GameReview $gameReview, BlogPostDraft $draft): void
    {
        $gameReviewDraft = GameReviewDraft::create([
            'blog_post_draft_id' => $draft->id,
            'game_title' => $gameReview->game_title,
            'release_date' => $gameReview->release_date,
            'genre' => $gameReview->genre,
            'developer' => $gameReview->developer,
            'publisher' => $gameReview->publisher,
            'platforms' => $gameReview->platforms,
            'cover_picture_id' => $gameReview->cover_picture_id,
            'pros_translation_key_id' => $gameReview->pros_translation_key_id,
            'cons_translation_key_id' => $gameReview->cons_translation_key_id,
            'score' => $gameReview->score,
        ]);

        foreach ($gameReview->links as $link) {
            $gameReviewDraft->links()->create([
                'type' => $link->type,
                'url' => $link->url,
                'label_translation_key_id' => $link->label_translation_key_id,
                'order' => $link->order,
            ]);
        }
    }

    /**
     * Publish a draft to a blog post
     */
    public function publishDraft(BlogPostDraft $draft): BlogPost
    {
        return DB::transaction(function () use ($draft) {
            $postData = [
                'slug' => $draft->slug,
                'type' => $draft->type,
                'status' => 'published',
                'category_id' => $draft->category_id,
                'cover_picture_id' => $draft->cover_picture_id,
                'published_at' => now(),
            ];

            if ($draft->blog_post_id) {
                $post = BlogPost::find($draft->blog_post_id);
                $post->update($postData);
            } else {
                $post = BlogPost::create($postData);
                $draft->update(['blog_post_id' => $post->id]);
            }

            // Clear existing contents
            $post->contents()->delete();

            // Copy draft contents to post
            foreach ($draft->contents as $content) {
                $post->contents()->create([
                    'content_type' => $content->content_type,
                    'content_id' => $content->content_id,
                    'order' => $content->order,
                ]);
            }

            // Handle game review if applicable
            if ($draft->type === 'game_review' && $draft->gameReviewDraft) {
                $this->publishGameReview($draft->gameReviewDraft, $post);
            }

            return $post->fresh(['contents', 'gameReview']);
        });
    }

    /**
     * Publish game review from draft
     */
    private function publishGameReview(GameReviewDraft $gameReviewDraft, BlogPost $post): void
    {
        // Delete existing game review if any
        GameReview::where('blog_post_id', $post->id)->delete();

        $gameReview = GameReview::create([
            'blog_post_id' => $post->id,
            'game_title' => $gameReviewDraft->game_title,
            'release_date' => $gameReviewDraft->release_date,
            'genre' => $gameReviewDraft->genre,
            'developer' => $gameReviewDraft->developer,
            'publisher' => $gameReviewDraft->publisher,
            'platforms' => $gameReviewDraft->platforms,
            'cover_picture_id' => $gameReviewDraft->cover_picture_id,
            'pros_translation_key_id' => $gameReviewDraft->pros_translation_key_id,
            'cons_translation_key_id' => $gameReviewDraft->cons_translation_key_id,
            'score' => $gameReviewDraft->score,
        ]);

        // Delete existing links and copy from draft
        $gameReview->links()->delete();

        foreach ($gameReviewDraft->links as $link) {
            $gameReview->links()->create([
                'type' => $link->type,
                'url' => $link->url,
                'label_translation_key_id' => $link->label_translation_key_id,
                'order' => $link->order,
            ]);
        }
    }

    /**
     * Update a draft
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(BlogPostDraft $draft, array $data): BlogPostDraft
    {
        $draft->update($data);

        return $draft->fresh();
    }

    /**
     * Delete a draft and its relations
     */
    public function deleteDraft(BlogPostDraft $draft): bool
    {
        return DB::transaction(function () use ($draft) {
            $draft->contents()->delete();

            if ($draft->gameReviewDraft) {
                $draft->gameReviewDraft->links()->delete();
                $draft->gameReviewDraft->delete();
            }

            return $draft->delete();
        });
    }

    /**
     * Delete a post and its relations
     */
    public function deletePost(BlogPost $post): bool
    {
        return DB::transaction(function () use ($post) {
            // Delete draft if exists
            if ($post->draft) {
                $this->deleteDraft($post->draft);
            }

            // Delete contents
            $post->contents()->delete();

            // Delete game review if exists
            if ($post->gameReview) {
                $post->gameReview->links()->delete();
                $post->gameReview->delete();
            }

            return $post->delete();
        });
    }

    /**
     * Get paginated published blog posts
     */
    public function getPaginatedPosts(int $perPage = 10, ?int $categoryId = null, ?string $type = null): LengthAwarePaginator
    {
        $query = BlogPost::published()
            ->with(['category', 'coverPicture'])
            ->orderBy('published_at', 'desc');

        if ($categoryId !== null) {
            $query->byCategory($categoryId);
        }

        if ($type !== null) {
            $query->byType($type);
        }

        return $query->paginate($perPage);
    }

    /**
     * Find a published blog post by slug
     */
    public function findBySlug(string $slug): ?BlogPost
    {
        return BlogPost::published()
            ->where('slug', $slug)
            ->with([
                'category',
                'coverPicture',
                'contents' => function ($query) {
                    $query->orderBy('order');
                },
                'contents.content',
                'gameReview',
                'gameReview.coverPicture',
                'gameReview.links',
            ])
            ->first();
    }

    /**
     * Get recent posts for homepage
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentPosts(int $limit = 5)
    {
        return BlogPost::published()
            ->with(['category', 'coverPicture'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
