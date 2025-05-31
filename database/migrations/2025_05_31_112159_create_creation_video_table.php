<?php

use App\Models\Creation;
use App\Models\Video;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_video', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Creation::class)->constrained('creations')->onDelete('cascade');
            $table->foreignIdFor(Video::class)->constrained('videos')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['creation_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_video');
    }
};
