<?php

use App\Models\Creation;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Creation::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Tag::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['creation_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_tag');
    }
};
