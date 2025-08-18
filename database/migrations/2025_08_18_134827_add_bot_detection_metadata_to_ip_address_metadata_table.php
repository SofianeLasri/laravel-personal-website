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
        Schema::table('ip_address_metadata', function (Blueprint $table) {
            $table->float('avg_request_interval')->nullable()->after('lon');
            $table->integer('total_requests')->default(0)->after('avg_request_interval');
            $table->timestamp('first_seen_at')->nullable()->after('total_requests');
            $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');
            $table->timestamp('last_bot_analysis_at')->nullable()->after('last_seen_at');

            $table->index('last_bot_analysis_at');
            $table->index(['avg_request_interval', 'total_requests']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ip_address_metadata', function (Blueprint $table) {
            $table->dropIndex(['avg_request_interval', 'total_requests']);
            $table->dropIndex(['last_bot_analysis_at']);

            $table->dropColumn('avg_request_interval');
            $table->dropColumn('total_requests');
            $table->dropColumn('first_seen_at');
            $table->dropColumn('last_seen_at');
            $table->dropColumn('last_bot_analysis_at');
        });
    }
};
