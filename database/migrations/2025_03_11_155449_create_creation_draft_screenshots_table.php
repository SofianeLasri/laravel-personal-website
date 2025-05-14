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
        Schema::create('creation_draft_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CreationDraft::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Picture::class)->constrained('pictures')->cascadeOnDelete();
            $table->foreignIdFor(TranslationKey::class, 'caption_translation_key_id')->nullable()->constrained('translation_keys')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_draft_screenshots');
    }
};
