<?php

use App\Models\CreationDraft;
use App\Models\Technology;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_draft_technology', function (Blueprint $table) {
            $table->foreignIdFor(CreationDraft::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Technology::class)->constrained()->cascadeOnDelete();
            $table->unique(['creation_draft_id', 'technology_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_draft_technology');
    }
};
