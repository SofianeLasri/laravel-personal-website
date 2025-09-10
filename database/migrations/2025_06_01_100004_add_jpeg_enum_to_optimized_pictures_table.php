<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support changing enum constraints directly
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we need to recreate the table
            Schema::dropIfExists('optimized_pictures_temp');

            // Create a new temp table with the updated format enum
            Schema::create('optimized_pictures_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('picture_id')->constrained('pictures');
                $table->enum('variant', ['thumbnail', 'small', 'medium', 'large', 'full']);
                $table->string('path');
                $table->enum('format', ['avif', 'webp', 'jpg']);
            });

            // Copy existing data
            DB::statement('INSERT INTO optimized_pictures_temp SELECT * FROM optimized_pictures');

            // Drop the old table
            Schema::drop('optimized_pictures');

            // Rename temp table
            Schema::rename('optimized_pictures_temp', 'optimized_pictures');
        } else {
            Schema::table('optimized_pictures', function (Blueprint $table) {
                $table->enum('format', ['avif', 'webp', 'jpg'])->change();
            });
        }
    }

    public function down(): void
    {
        // SQLite doesn't support changing enum constraints directly
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we need to recreate the table
            Schema::dropIfExists('optimized_pictures_temp');

            // Create a new temp table with the original format enum
            Schema::create('optimized_pictures_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('picture_id')->constrained('pictures');
                $table->enum('variant', ['thumbnail', 'small', 'medium', 'large', 'full']);
                $table->string('path');
                $table->enum('format', ['avif', 'webp']);
            });

            // Copy existing data (excluding jpg format entries)
            DB::statement("INSERT INTO optimized_pictures_temp SELECT * FROM optimized_pictures WHERE format IN ('avif', 'webp')");

            // Drop the old table
            Schema::drop('optimized_pictures');

            // Rename temp table
            Schema::rename('optimized_pictures_temp', 'optimized_pictures');
        } else {
            // First, delete any jpg format entries that might exist
            DB::table('optimized_pictures')->where('format', 'jpg')->delete();
            
            // Then change the enum
            Schema::table('optimized_pictures', function (Blueprint $table) {
                $table->enum('format', ['avif', 'webp'])->change();
            });
        }
    }
};
