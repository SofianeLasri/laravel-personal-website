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
        Schema::table('screenshots', function (Blueprint $table) {
            $table->unsignedInteger('order')->nullable()->after('caption_translation_key_id');
            $table->index('order');
        });

        Schema::table('creation_draft_screenshots', function (Blueprint $table) {
            $table->unsignedInteger('order')->nullable()->after('caption_translation_key_id');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropIndex(['order']);
            $table->dropColumn('order');
        });

        Schema::table('creation_draft_screenshots', function (Blueprint $table) {
            $table->dropIndex(['order']);
            $table->dropColumn('order');
        });
    }
};
