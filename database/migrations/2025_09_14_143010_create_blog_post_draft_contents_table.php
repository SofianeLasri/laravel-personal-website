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
        Schema::create('blog_post_draft_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_draft_id')->constrained('blog_post_drafts')->onDelete('cascade');
            $table->string('content_type');
            $table->unsignedBigInteger('content_id');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['blog_post_draft_id', 'order']);
            $table->index(['content_type', 'content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_post_draft_contents');
    }
};
