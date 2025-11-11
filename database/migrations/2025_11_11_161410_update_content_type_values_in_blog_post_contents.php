<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update content_type values in blog_post_contents table
        DB::table('blog_post_contents')
            ->where('content_type', 'App\\Models\\BlogContentMarkdown')
            ->update(['content_type' => 'App\\Models\\ContentMarkdown']);

        DB::table('blog_post_contents')
            ->where('content_type', 'App\\Models\\BlogContentGallery')
            ->update(['content_type' => 'App\\Models\\ContentGallery']);

        DB::table('blog_post_contents')
            ->where('content_type', 'App\\Models\\BlogContentVideo')
            ->update(['content_type' => 'App\\Models\\ContentVideo']);

        // Update content_type values in blog_post_draft_contents table
        DB::table('blog_post_draft_contents')
            ->where('content_type', 'App\\Models\\BlogContentMarkdown')
            ->update(['content_type' => 'App\\Models\\ContentMarkdown']);

        DB::table('blog_post_draft_contents')
            ->where('content_type', 'App\\Models\\BlogContentGallery')
            ->update(['content_type' => 'App\\Models\\ContentGallery']);

        DB::table('blog_post_draft_contents')
            ->where('content_type', 'App\\Models\\BlogContentVideo')
            ->update(['content_type' => 'App\\Models\\ContentVideo']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert content_type values in blog_post_contents table
        DB::table('blog_post_contents')
            ->where('content_type', 'App\\Models\\ContentMarkdown')
            ->update(['content_type' => 'App\\Models\\BlogContentMarkdown']);

        DB::table('blog_post_contents')
            ->where('content_type', 'App\\Models\\ContentGallery')
            ->update(['content_type' => 'App\\Models\\BlogContentGallery']);

        DB::table('blog_post_contents')
            ->where('content_type', 'App\\Models\\ContentVideo')
            ->update(['content_type' => 'App\\Models\\BlogContentVideo']);

        // Revert content_type values in blog_post_draft_contents table
        DB::table('blog_post_draft_contents')
            ->where('content_type', 'App\\Models\\ContentMarkdown')
            ->update(['content_type' => 'App\\Models\\BlogContentMarkdown']);

        DB::table('blog_post_draft_contents')
            ->where('content_type', 'App\\Models\\ContentGallery')
            ->update(['content_type' => 'App\\Models\\BlogContentGallery']);

        DB::table('blog_post_draft_contents')
            ->where('content_type', 'App\\Models\\ContentVideo')
            ->update(['content_type' => 'App\\Models\\BlogContentVideo']);
    }
};
