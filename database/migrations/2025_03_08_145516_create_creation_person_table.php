<?php

use App\Models\Creation;
use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creation_person', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Creation::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Person::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['creation_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creation_person');
    }
};
