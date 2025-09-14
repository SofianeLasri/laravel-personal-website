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
        Schema::create('blog_content_galleries', function (Blueprint $table) {
            $table->id();
            $table->string('layout')->default('grid');
            $table->integer('columns')->nullable()->default(2);
            $table->timestamps();

            $table->index(['layout', 'columns']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_content_galleries');
    }
};
