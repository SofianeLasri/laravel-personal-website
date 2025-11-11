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
        Schema::rename('blog_content_markdown', 'content_markdowns');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('content_markdowns', 'blog_content_markdown');
    }
};
