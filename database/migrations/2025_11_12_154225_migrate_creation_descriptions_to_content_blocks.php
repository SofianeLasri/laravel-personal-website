<?php

use App\Models\ContentMarkdown;
use App\Models\Creation;
use App\Models\CreationDraft;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts existing full_description_translation_key_id to content blocks
     */
    public function up(): void
    {
        // Migrate Creation descriptions
        DB::transaction(function () {
            $creations = Creation::whereNotNull('full_description_translation_key_id')
                ->whereDoesntHave('contents')
                ->get();

            foreach ($creations as $creation) {
                // Create ContentMarkdown entry
                $markdown = ContentMarkdown::create([
                    'translation_key_id' => $creation->full_description_translation_key_id,
                ]);

                // Create CreationContent pivot entry
                $creation->contents()->create([
                    'content_type' => ContentMarkdown::class,
                    'content_id' => $markdown->id,
                    'order' => 1,
                ]);
            }
        });

        // Migrate CreationDraft descriptions
        DB::transaction(function () {
            $drafts = CreationDraft::whereNotNull('full_description_translation_key_id')
                ->whereDoesntHave('contents')
                ->get();

            foreach ($drafts as $draft) {
                // Create ContentMarkdown entry
                $markdown = ContentMarkdown::create([
                    'translation_key_id' => $draft->full_description_translation_key_id,
                ]);

                // Create CreationDraftContent pivot entry
                $draft->contents()->create([
                    'content_type' => ContentMarkdown::class,
                    'content_id' => $markdown->id,
                    'order' => 1,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Note: This is a destructive operation that removes content blocks.
     * The original full_description_translation_key_id fields are preserved.
     */
    public function down(): void
    {
        // Delete all CreationContent entries and their associated ContentMarkdown
        DB::transaction(function () {
            $contents = DB::table('creation_contents')->get();

            foreach ($contents as $content) {
                // Delete the ContentMarkdown if it exists
                if ($content->content_type === ContentMarkdown::class) {
                    DB::table('content_markdowns')->where('id', $content->content_id)->delete();
                }
            }

            // Delete all CreationContent entries
            DB::table('creation_contents')->delete();
        });

        // Delete all CreationDraftContent entries and their associated ContentMarkdown
        DB::transaction(function () {
            $contents = DB::table('creation_draft_contents')->get();

            foreach ($contents as $content) {
                // Delete the ContentMarkdown if it exists
                if ($content->content_type === ContentMarkdown::class) {
                    DB::table('content_markdowns')->where('id', $content->content_id)->delete();
                }
            }

            // Delete all CreationDraftContent entries
            DB::table('creation_draft_contents')->delete();
        });
    }
};
