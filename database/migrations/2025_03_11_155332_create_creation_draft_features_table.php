<?php

use App\Models\CreationDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_draft_features', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CreationDraft::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TranslationKey::class, 'title_translation_key_id')->constrained('translation_keys');
            $table->foreignIdFor(TranslationKey::class, 'description_translation_key_id')->constrained('translation_keys');
            $table->foreignIdFor(Picture::class)->nullable()->constrained('pictures')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_draft_features');
    }
};
