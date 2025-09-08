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
        Schema::table('optimized_pictures', function (Blueprint $table) {
            // Index composite pour les requêtes fréquentes de récupération
            // par picture_id avec variant et format spécifiques
            $table->index(['picture_id', 'variant', 'format'], 'idx_picture_variant_format');

            // Index pour les requêtes groupées par picture_id
            // (utilisé dans OptimizePicturesCommand)
            $table->index(['picture_id'], 'idx_picture_id');

            // Index pour rechercher par variant ou format spécifique
            $table->index(['variant'], 'idx_variant');
            $table->index(['format'], 'idx_format');

            // Index pour optimiser les jointures et comptes
            $table->index(['picture_id', 'id'], 'idx_picture_id_with_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('optimized_pictures', function (Blueprint $table) {
            // Drop foreign key constraint temporarily to allow dropping indexes
            $table->dropForeign(['picture_id']);
        });

        Schema::table('optimized_pictures', function (Blueprint $table) {
            // Now we can drop the indexes
            $table->dropIndex('idx_picture_variant_format');
            $table->dropIndex('idx_picture_id');
            $table->dropIndex('idx_variant');
            $table->dropIndex('idx_format');
            $table->dropIndex('idx_picture_id_with_id');
        });

        Schema::table('optimized_pictures', function (Blueprint $table) {
            // Re-add the foreign key constraint
            $table->foreign('picture_id')->references('id')->on('pictures');
        });
    }
};
