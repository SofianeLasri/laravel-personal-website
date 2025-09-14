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
        Schema::create('blog_content_gallery_pictures', function (Blueprint $table) {
            $table->foreignId('gallery_id')->constrained('blog_content_galleries')->onDelete('cascade');
            $table->foreignId('picture_id')->constrained('pictures')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->foreignId('caption_translation_key_id')->nullable()->constrained('translation_keys')->onDelete('set null');

            $table->primary(['gallery_id', 'picture_id']);
            $table->index(['gallery_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_content_gallery_pictures');
    }
};
