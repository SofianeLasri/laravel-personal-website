<?php

use App\Models\Experience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add slug column (nullable initially to allow for existing data)
        Schema::table('experiences', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('organization_name');
        });

        // Generate slugs for existing experiences based on organization_name
        $experiences = Experience::with('titleTranslationKey.translations')->get();

        foreach ($experiences as $experience) {
            // Try to get the title translation
            $titleTranslation = $experience->titleTranslationKey->translations
                ->where('locale', 'fr')
                ->first();

            if (! $titleTranslation) {
                // Fallback to any available translation
                $titleTranslation = $experience->titleTranslationKey->translations->first();
            }

            // Generate slug from organization_name and title if available
            if ($titleTranslation) {
                $baseSlug = Str::slug($experience->organization_name.'-'.$titleTranslation->text);
            } else {
                // Fallback to just organization_name
                $baseSlug = Str::slug($experience->organization_name);
            }

            // Ensure uniqueness
            $slug = $baseSlug;
            $counter = 1;

            while (Experience::where('slug', $slug)->where('id', '!=', $experience->id)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }

            // Update the experience with the generated slug
            $experience->slug = $slug;
            $experience->save();
        }

        // Now make the column non-nullable and add unique index
        Schema::table('experiences', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
