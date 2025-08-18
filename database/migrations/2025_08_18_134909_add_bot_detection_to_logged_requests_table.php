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
            $table->boolean('is_bot_by_frequency')->default(false)->after('user_id');
            $table->boolean('is_bot_by_user_agent')->default(false)->after('is_bot_by_frequency');
            $table->boolean('is_bot_by_parameters')->default(false)->after('is_bot_by_user_agent');
            $table->json('bot_detection_metadata')->nullable()->after('is_bot_by_parameters');
            $table->timestamp('bot_analyzed_at')->nullable()->after('bot_detection_metadata');

            $table->index('bot_analyzed_at');
            $table->index(['is_bot_by_frequency', 'is_bot_by_user_agent', 'is_bot_by_parameters'], 'bot_detection_flags_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logged_requests', function (Blueprint $table) {
            $table->dropIndex('bot_detection_flags_index');
            $table->dropIndex(['bot_analyzed_at']);

            $table->dropColumn('is_bot_by_frequency');
            $table->dropColumn('is_bot_by_user_agent');
            $table->dropColumn('is_bot_by_parameters');
            $table->dropColumn('bot_detection_metadata');
            $table->dropColumn('bot_analyzed_at');
        });
    }
};
