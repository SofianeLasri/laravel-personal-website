<?php

use App\Models\CreationDraft;
use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_draft_person', function (Blueprint $table) {
            $table->foreignIdFor(CreationDraft::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Person::class)->constrained()->cascadeOnDelete();
            $table->unique(['creation_draft_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_draft_person');
    }
};
