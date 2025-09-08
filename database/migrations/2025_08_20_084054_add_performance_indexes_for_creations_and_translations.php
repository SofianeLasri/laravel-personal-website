<?php

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
        // Indexes pour la table creations
        Schema::table('creations', function (Blueprint $table) {
            // Index pour les requêtes filtrées par type (très utilisé dans PublicControllersService)
            $table->index(['type'], 'idx_creation_type');

            // Index composite pour les tris fréquents (ended_at IS NULL DESC, ended_at DESC)
            $table->index(['ended_at'], 'idx_creation_ended_at');

            // Index pour améliorer les requêtes avec featured
            $table->index(['featured', 'type'], 'idx_creation_featured_type');

            // Index pour les clés étrangères fréquemment jointes
            $table->index(['short_description_translation_key_id'], 'idx_creation_short_desc_key');
            $table->index(['full_description_translation_key_id'], 'idx_creation_full_desc_key');

            // Index composite pour optimiser les requêtes de tri avec type
            $table->index(['type', 'ended_at'], 'idx_creation_type_ended');
        });

        // Indexes pour la table translations (déjà partiellement indexée)
        // Note: translation_key_id et locale sont déjà indexés individuellement
        // L'unique index sur [translation_key_id, locale] sert aussi d'index composite

        // Indexes pour la table features
        Schema::table('features', function (Blueprint $table) {
            // Index pour les jointures avec creations
            $table->index(['creation_id'], 'idx_feature_creation');

            // Index pour les clés de traduction
            $table->index(['title_translation_key_id'], 'idx_feature_title_key');
            $table->index(['description_translation_key_id'], 'idx_feature_desc_key');
        });

        // Indexes pour la table experiences
        Schema::table('experiences', function (Blueprint $table) {
            // Index pour filtrer par type
            $table->index(['type'], 'idx_experience_type');

            // Index pour les tris par date
            $table->index(['started_at', 'ended_at'], 'idx_experience_dates');

            // Index pour les clés de traduction
            $table->index(['title_translation_key_id'], 'idx_experience_title_key');
            $table->index(['short_description_translation_key_id'], 'idx_experience_short_desc_key');
            $table->index(['full_description_translation_key_id'], 'idx_experience_full_desc_key');

            // Index composite pour type et dates (requêtes fréquentes)
            $table->index(['type', 'started_at'], 'idx_experience_type_started');
        });

        // Indexes pour la table experience_technology (déjà bien indexée avec unique constraint)
        // L'unique index sert déjà d'index composite efficace

        // Indexes pour la table creation_technology
        Schema::table('creation_technology', function (Blueprint $table) {
            // Index pour les jointures inverses (technology vers creations)
            $table->index(['technology_id', 'creation_id'], 'idx_tech_creation');

            // Index pour les jointures depuis creation
            $table->index(['creation_id'], 'idx_creation_tech');
        });

        // Indexes pour la table technologies
        Schema::table('technologies', function (Blueprint $table) {
            // Index pour recherche par nom (Laravel dans getLaravelCreations)
            $table->index(['name'], 'idx_technology_name');

            // Index pour filtrer par type
            $table->index(['type'], 'idx_technology_type');

            // Index pour la clé de description
            $table->index(['description_translation_key_id'], 'idx_technology_desc_key');
        });

        // Indexes pour la table screenshots
        Schema::table('screenshots', function (Blueprint $table) {
            // Index pour les jointures avec creations
            $table->index(['creation_id'], 'idx_screenshot_creation');

            // Index pour la clé de caption
            $table->index(['caption_translation_key_id'], 'idx_screenshot_caption_key');
        });

        // Indexes pour la table videos
        Schema::table('videos', function (Blueprint $table) {
            // Index pour filtrer par status et visibility
            $table->index(['status', 'visibility'], 'idx_video_status_visibility');
        });

        // Indexes pour la table creation_video
        Schema::table('creation_video', function (Blueprint $table) {
            // Index pour les jointures depuis creation
            $table->index(['creation_id'], 'idx_creation_video');

            // Index pour les jointures depuis video
            $table->index(['video_id', 'creation_id'], 'idx_video_creation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints first for tables that have indexes on foreign key columns
        Schema::table('creations', function (Blueprint $table) {
            $table->dropForeign(['short_description_translation_key_id']);
            $table->dropForeign(['full_description_translation_key_id']);
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropForeign(['creation_id']);
            $table->dropForeign(['title_translation_key_id']);
            $table->dropForeign(['description_translation_key_id']);
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['title_translation_key_id']);
            $table->dropForeign(['short_description_translation_key_id']);
            $table->dropForeign(['full_description_translation_key_id']);
        });

        Schema::table('creation_technology', function (Blueprint $table) {
            $table->dropForeign(['creation_id']);
            $table->dropForeign(['technology_id']);
        });

        Schema::table('technologies', function (Blueprint $table) {
            $table->dropForeign(['description_translation_key_id']);
        });

        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropForeign(['creation_id']);
            if (Schema::hasColumn('screenshots', 'caption_translation_key_id')) {
                $table->dropForeign(['caption_translation_key_id']);
            }
        });

        Schema::table('creation_video', function (Blueprint $table) {
            $table->dropForeign(['creation_id']);
            $table->dropForeign(['video_id']);
        });

        // Now drop all indexes
        Schema::table('creations', function (Blueprint $table) {
            $table->dropIndex('idx_creation_type');
            $table->dropIndex('idx_creation_ended_at');
            $table->dropIndex('idx_creation_featured_type');
            $table->dropIndex('idx_creation_short_desc_key');
            $table->dropIndex('idx_creation_full_desc_key');
            $table->dropIndex('idx_creation_type_ended');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropIndex('idx_feature_creation');
            $table->dropIndex('idx_feature_title_key');
            $table->dropIndex('idx_feature_desc_key');
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropIndex('idx_experience_type');
            $table->dropIndex('idx_experience_dates');
            $table->dropIndex('idx_experience_title_key');
            $table->dropIndex('idx_experience_short_desc_key');
            $table->dropIndex('idx_experience_full_desc_key');
            $table->dropIndex('idx_experience_type_started');
        });

        Schema::table('creation_technology', function (Blueprint $table) {
            $table->dropIndex('idx_tech_creation');
            $table->dropIndex('idx_creation_tech');
        });

        Schema::table('technologies', function (Blueprint $table) {
            $table->dropIndex('idx_technology_name');
            $table->dropIndex('idx_technology_type');
            $table->dropIndex('idx_technology_desc_key');
        });

        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropIndex('idx_screenshot_creation');
            $table->dropIndex('idx_screenshot_caption_key');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('idx_video_status_visibility');
        });

        Schema::table('creation_video', function (Blueprint $table) {
            $table->dropIndex('idx_creation_video');
            $table->dropIndex('idx_video_creation');
        });

        // Re-add all foreign key constraints
        Schema::table('creations', function (Blueprint $table) {
            $table->foreign('short_description_translation_key_id')->references('id')->on('translation_keys');
            $table->foreign('full_description_translation_key_id')->references('id')->on('translation_keys');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->foreign('creation_id')->references('id')->on('creations')->cascadeOnDelete();
            $table->foreign('title_translation_key_id')->references('id')->on('translation_keys');
            $table->foreign('description_translation_key_id')->references('id')->on('translation_keys');
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->foreign('title_translation_key_id')->references('id')->on('translation_keys');
            $table->foreign('short_description_translation_key_id')->references('id')->on('translation_keys');
            $table->foreign('full_description_translation_key_id')->references('id')->on('translation_keys');
        });

        Schema::table('creation_technology', function (Blueprint $table) {
            $table->foreign('creation_id')->references('id')->on('creations')->cascadeOnDelete();
            $table->foreign('technology_id')->references('id')->on('technologies')->cascadeOnDelete();
        });

        Schema::table('technologies', function (Blueprint $table) {
            $table->foreign('description_translation_key_id')->references('id')->on('translation_keys');
        });

        Schema::table('screenshots', function (Blueprint $table) {
            $table->foreign('creation_id')->references('id')->on('creations')->cascadeOnDelete();
            if (Schema::hasColumn('screenshots', 'caption_translation_key_id')) {
                $table->foreign('caption_translation_key_id')->references('id')->on('translation_keys')->nullOnDelete();
            }
        });

        Schema::table('creation_video', function (Blueprint $table) {
            $table->foreign('creation_id')->references('id')->on('creations')->cascadeOnDelete();
            $table->foreign('video_id')->references('id')->on('videos')->cascadeOnDelete();
        });
    }
};
