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
     */
    public function up(): void
    {
        // Migrate published creations
        $creations = Creation::whereNotNull('full_description_translation_key_id')->get();

        foreach ($creations as $creation) {
            // Create ContentMarkdown for the full description
            $markdown = ContentMarkdown::create([
                'translation_key_id' => $creation->full_description_translation_key_id,
            ]);

            // Create CreationContent linking to this markdown
            DB::table('creation_contents')->insert([
                'creation_id' => $creation->id,
                'content_type' => ContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Migrate draft creations
        $drafts = CreationDraft::whereNotNull('full_description_translation_key_id')->get();

        foreach ($drafts as $draft) {
            // Create ContentMarkdown for the full description
            $markdown = ContentMarkdown::create([
                'translation_key_id' => $draft->full_description_translation_key_id,
            ]);

            // Create CreationDraftContent linking to this markdown
            DB::table('creation_draft_contents')->insert([
                'creation_draft_id' => $draft->id,
                'content_type' => ContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete all creation content blocks
        DB::table('creation_contents')->delete();
        DB::table('creation_draft_contents')->delete();

        // Note: This will leave orphaned ContentMarkdown records
        // but they will be cleaned up by the down migration of the content tables
    }
};
