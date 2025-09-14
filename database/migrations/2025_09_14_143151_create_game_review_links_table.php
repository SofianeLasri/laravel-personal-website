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
        Schema::create('game_review_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_review_id')->constrained('game_reviews')->onDelete('cascade');
            $table->string('type');
            $table->string('url');
            $table->foreignId('label_translation_key_id')->constrained('translation_keys')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['game_review_id', 'order']);
            $table->index(['type', 'game_review_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_review_links');
    }
};
