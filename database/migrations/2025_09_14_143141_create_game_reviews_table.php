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
        Schema::create('game_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->onDelete('cascade');
            $table->string('game_title');
            $table->date('release_date')->nullable();
            $table->string('genre')->nullable();
            $table->string('developer')->nullable();
            $table->string('publisher')->nullable();
            $table->json('platforms')->nullable();
            $table->foreignId('cover_picture_id')->nullable()->constrained('pictures')->onDelete('set null');
            $table->foreignId('pros_translation_key_id')->nullable()->constrained('translation_keys')->onDelete('set null');
            $table->foreignId('cons_translation_key_id')->nullable()->constrained('translation_keys')->onDelete('set null');
            $table->enum('rating', ['positive', 'negative'])->nullable();
            $table->timestamps();

            $table->index('blog_post_id');
            $table->index(['genre', 'rating']);
            $table->index('game_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_reviews');
    }
};
