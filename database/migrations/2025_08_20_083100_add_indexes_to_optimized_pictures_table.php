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
            // Note: picture_id a déjà un index via la contrainte de clé étrangère
            // Créons plutôt un index composite sur variant et format uniquement
            $table->index(['variant', 'format'], 'idx_variant_format');

            // Index pour les requêtes groupées par picture_id
            // Note: Cet index est créé automatiquement par MySQL/MariaDB pour la contrainte de clé étrangère
            // $table->index(['picture_id'], 'idx_picture_id');

            // Index pour rechercher par variant ou format spécifique
            // Commenté car déjà inclus dans l'index composite ci-dessus
            // $table->index(['variant'], 'idx_variant');
            // $table->index(['format'], 'idx_format');

            // Index pour optimiser les jointures et comptes
            // Note: picture_id a déjà un index automatique via la contrainte de clé étrangère
            // $table->index(['picture_id', 'id'], 'idx_picture_id_with_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('optimized_pictures', function (Blueprint $table) {
            $table->dropIndex('idx_variant_format');
            // These indexes were not created or are managed by foreign key constraints
            // $table->dropIndex('idx_picture_variant_format');
            // $table->dropIndex('idx_picture_id');
            // $table->dropIndex('idx_variant');
            // $table->dropIndex('idx_format');
            // $table->dropIndex('idx_picture_id_with_id');
        });
    }
};
