<?php

use App\Models\TranslationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TranslationKey::class)->constrained('translation_keys');
            $table->enum('locale', ['en', 'fr'])->default('fr');
            $table->text('text');

            $table->unique(['translation_key_id', 'locale']);
            $table->index('translation_key_id');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
