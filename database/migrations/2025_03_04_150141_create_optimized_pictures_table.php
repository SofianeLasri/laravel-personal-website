<?php

use App\Models\Picture;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optimized_pictures', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Picture::class)->constrained('pictures');
            $table->enum('variant', ['thumbnail', 'small', 'medium', 'large', 'full']);
            $table->string('path');
            $table->enum('format', ['avif', 'webp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimized_pictures');
    }
};
