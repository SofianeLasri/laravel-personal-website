<?php

use App\Models\CreationDraft;
use App\Models\Video;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_draft_video', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CreationDraft::class)->constrained('creation_drafts')->onDelete('cascade');
            $table->foreignIdFor(Video::class)->constrained('videos')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['creation_draft_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_draft_video');
    }
};
