<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Populate screenshots order based on ID (natural order)
        $screenshots = DB::table('screenshots')
            ->orderBy('creation_id')
            ->orderBy('id')
            ->get();

        $orderByCreation = [];
        foreach ($screenshots as $screenshot) {
            if (! isset($orderByCreation[$screenshot->creation_id])) {
                $orderByCreation[$screenshot->creation_id] = 1;
            }

            DB::table('screenshots')
                ->where('id', $screenshot->id)
                ->update(['order' => $orderByCreation[$screenshot->creation_id]]);

            $orderByCreation[$screenshot->creation_id]++;
        }

        // Populate creation_draft_screenshots order based on ID (natural order)
        $draftScreenshots = DB::table('creation_draft_screenshots')
            ->orderBy('creation_draft_id')
            ->orderBy('id')
            ->get();

        $orderByDraft = [];
        foreach ($draftScreenshots as $screenshot) {
            if (! isset($orderByDraft[$screenshot->creation_draft_id])) {
                $orderByDraft[$screenshot->creation_draft_id] = 1;
            }

            DB::table('creation_draft_screenshots')
                ->where('id', $screenshot->id)
                ->update(['order' => $orderByDraft[$screenshot->creation_draft_id]]);

            $orderByDraft[$screenshot->creation_draft_id]++;
        }

        // Make order NOT NULL using Laravel Schema Builder (SQLite compatible)
        Schema::table('screenshots', function (Blueprint $table) {
            $table->unsignedInteger('order')->nullable(false)->change();
            $table->unique(['creation_id', 'order'], 'unique_creation_order');
        });

        Schema::table('creation_draft_screenshots', function (Blueprint $table) {
            $table->unsignedInteger('order')->nullable(false)->change();
            $table->unique(['creation_draft_id', 'order'], 'unique_draft_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove unique constraints
        try {
            Schema::table('screenshots', function (Blueprint $table) {
                $table->dropUnique('unique_creation_order');
            });
        } catch (Exception) {
            // Index doesn't exist, skip
        }

        try {
            Schema::table('creation_draft_screenshots', function (Blueprint $table) {
                $table->dropUnique('unique_draft_order');
            });
        } catch (Exception) {
            // Index doesn't exist, skip
        }

        // Make order nullable again
        Schema::table('screenshots', function (Blueprint $table) {
            $table->unsignedInteger('order')->nullable()->change();
        });

        Schema::table('creation_draft_screenshots', function (Blueprint $table) {
            $table->unsignedInteger('order')->nullable()->change();
        });
    }
};
