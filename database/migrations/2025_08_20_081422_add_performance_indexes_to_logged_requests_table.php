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
        Schema::table('logged_requests', function (Blueprint $table) {
            // Index pour les requêtes de stats du dashboard (HomeController::stats)
            // Requête principale avec joins et filtres
            $table->index(['url_id', 'ip_address_id', 'created_at'], 'idx_stats_main');
            $table->index(['user_agent_id', 'created_at'], 'idx_user_agent_created');
            $table->index(['status_code', 'user_id', 'created_at'], 'idx_status_user_created');

            // Index pour améliorer la sous-requête d'exclusion des IPs connectées
            $table->index(['ip_address_id', 'user_id'], 'idx_ip_user');

            // Index pour les filtres de bot detection
            $table->index(['is_bot_by_frequency', 'is_bot_by_user_agent', 'is_bot_by_parameters', 'created_at'], 'idx_bot_flags_created');

            // Index pour la page des logs (RequestLogController::index)
            // Optimisation pour ORDER BY et filtres
            $table->index(['created_at'], 'idx_created_at');
            $table->index(['method', 'status_code'], 'idx_method_status');

            // Index pour les filtres de date range
            $table->index(['created_at', 'status_code'], 'idx_created_status');
        });

        // Indexes pour les tables associées si elles n'existent pas déjà

        // Table ip_addresses
        if (! Schema::hasIndex('ip_addresses', 'idx_ip')) {
            Schema::table('ip_addresses', function (Blueprint $table) {
                $table->index(['ip'], 'idx_ip');
            });
        }

        // Table user_agents
        if (! Schema::hasIndex('user_agents', 'idx_user_agent')) {
            Schema::table('user_agents', function (Blueprint $table) {
                $table->index(['user_agent'], 'idx_user_agent');
            });
        }

        // Table urls
        if (! Schema::hasIndex('urls', 'idx_url')) {
            Schema::table('urls', function (Blueprint $table) {
                $table->index(['url'], 'idx_url');
            });
        }

        // Table ip_address_metadata
        if (! Schema::hasIndex('ip_address_metadata', 'idx_ip_metadata')) {
            Schema::table('ip_address_metadata', function (Blueprint $table) {
                $table->index(['ip_address_id', 'country_code'], 'idx_ip_metadata');
                $table->index(['total_requests', 'avg_request_interval'], 'idx_request_stats');
            });
        }

        // Table user_agent_metadata
        if (! Schema::hasIndex('user_agent_metadata', 'idx_ua_metadata')) {
            Schema::table('user_agent_metadata', function (Blueprint $table) {
                $table->index(['user_agent_id', 'is_bot'], 'idx_ua_metadata');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logged_requests', function (Blueprint $table) {
            $table->dropIndex('idx_stats_main');
            $table->dropIndex('idx_user_agent_created');
            $table->dropIndex('idx_status_user_created');
            $table->dropIndex('idx_ip_user');
            $table->dropIndex('idx_bot_flags_created');
            $table->dropIndex('idx_created_at');
            $table->dropIndex('idx_method_status');
            $table->dropIndex('idx_created_status');
        });

        // Suppression des index sur les tables associées
        Schema::table('ip_addresses', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_ip');
        });

        Schema::table('user_agents', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_user_agent');
        });

        Schema::table('urls', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_url');
        });

        Schema::table('ip_address_metadata', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_ip_metadata');
            $table->dropIndexIfExists('idx_request_stats');
        });

        Schema::table('user_agent_metadata', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_ua_metadata');
        });
    }
};
