<?php

use App\Models\Picture;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level');
            $table->string('score');
            $table->date('date');
            $table->string('link');
            $table->foreignIdFor(Picture::class)->constrained('pictures');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
