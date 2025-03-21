<?php

use App\Models\Creation;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->foreignIdFor(Picture::class, 'logo_id')->nullable()->constrained('pictures')->nullOnDelete();
            $table->foreignIdFor(Picture::class, 'cover_image_id')->nullable()->constrained('pictures')->nullOnDelete();
            $table->enum('type', ['portfolio', 'game', 'library', 'website', 'tool', 'map', 'other']);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->foreignIdFor(TranslationKey::class, 'short_description_translation_key_id')->nullable()->constrained('translation_keys');
            $table->foreignIdFor(TranslationKey::class, 'full_description_translation_key_id')->nullable()->constrained('translation_keys');
            $table->string('external_url')->nullable();
            $table->string('source_code_url')->nullable();
            $table->boolean('featured')->default(false);
            $table->foreignIdFor(Creation::class, 'original_creation_id')->nullable()->constrained('creations')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_drafts');
    }
};
