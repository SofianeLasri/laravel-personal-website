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
        Schema::table('blog_content_galleries', function (Blueprint $table) {
            $table->string('layout')->default('grid')->after('id');
            $table->integer('columns')->nullable()->after('layout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_content_galleries', function (Blueprint $table) {
            $table->dropColumn(['layout', 'columns']);
        });
    }
};
