<?php

use App\Models\Technology;
use App\Models\TranslationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technology_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Technology::class)->constrained('technologies');
            $table->foreignIdFor(TranslationKey::class, 'description_translation_key_id')->constrained('translation_keys');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technology_experiences');
    }
};
