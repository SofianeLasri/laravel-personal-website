<?php

use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TranslationKey::class, 'title_translation_key_id')->constrained('translation_keys');
            $table->string('organization_name');
            $table->foreignIdFor(Picture::class, 'logo_id')->constrained('pictures');
            $table->enum('type', ['formation', 'emploi']);
            $table->string('location');
            $table->string('website_url')->nullable();
            $table->foreignIdFor(TranslationKey::class, 'short_description_translation_key_id')->constrained('translation_keys');
            $table->foreignIdFor(TranslationKey::class, 'full_description_translation_key_id')->constrained('translation_keys');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('experience_technology', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['experience_id', 'technology_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_technology');
        Schema::dropIfExists('experiences');
    }
};
