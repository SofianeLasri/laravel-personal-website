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
        Schema::rename('blog_content_galleries', 'content_galleries');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('content_galleries', 'blog_content_galleries');
    }
};
