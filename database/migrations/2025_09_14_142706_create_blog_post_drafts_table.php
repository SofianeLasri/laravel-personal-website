<?php

use App\Models\TranslationKey;
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
        Schema::create('blog_post_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->onDelete('cascade');
            $table->string('slug');
            $table->foreignIdFor(TranslationKey::class, 'title_translation_key_id')->constrained('translation_keys');
            $table->string('type'); // TODO: Use Enum
            $table->foreignId('category_id')->constrained('blog_categories')->onDelete('cascade');
            $table->foreignId('cover_picture_id')->nullable()->constrained('pictures')->onDelete('set null');
            $table->timestamps();

            $table->index(['blog_post_id']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_post_drafts');
    }
};
