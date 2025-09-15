<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blog_content_videos', function (Blueprint $table) {
            $table->dropForeign(['video_id']);
            $table->dropIndex(['video_id']);
            $table->foreignId('video_id')->nullable()->change()->constrained('videos')->onDelete('set null');
            $table->index('video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_content_videos', function (Blueprint $table) {
            $table->dropForeign(['video_id']);
            $table->dropIndex(['video_id']);
            $table->foreignId('video_id')->change()->constrained('videos')->onDelete('cascade');
            $table->index('video_id');
        });
    }
};
