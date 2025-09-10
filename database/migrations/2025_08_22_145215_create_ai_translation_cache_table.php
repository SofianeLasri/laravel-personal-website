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
        Schema::create('ai_translation_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique()->index();
            $table->string('provider', 20);
            $table->text('system_prompt');
            $table->text('user_prompt');
            $table->json('response');
            $table->integer('hits')->default(0);
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_translation_cache');
    }
};
