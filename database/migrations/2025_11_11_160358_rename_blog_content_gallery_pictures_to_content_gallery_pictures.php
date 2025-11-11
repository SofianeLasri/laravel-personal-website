<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('blog_content_gallery_pictures', 'content_gallery_pictures');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('content_gallery_pictures', 'blog_content_gallery_pictures');
    }
};
