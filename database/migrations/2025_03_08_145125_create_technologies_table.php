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
            $table->enum('type', ['framework', 'library', 'language', 'other']);
            $table->boolean('featured')->default(false);
            $table->text('svg_icon');
            $table->foreignIdFor(TranslationKey::class, 'description_translation_key_id')->constrained('translation_keys');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technologies');
    }
};
