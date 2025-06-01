<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('optimized_pictures', function (Blueprint $table) {
            $table->enum('format', ['avif', 'webp', 'jpg'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('optimized_pictures', function (Blueprint $table) {
            $table->enum('format', ['avif', 'webp'])->change();
        });
    }
};
