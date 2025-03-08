<?php

use App\Models\TranslationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('svg_icon');
            $table->foreignIdFor(TranslationKey::class, 'name_translation_key_id')->constrained('translation_keys');
            $table->foreignIdFor(TranslationKey::class, 'description_translation_key_id')->constrained('translation_keys');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technologies');
    }
};
