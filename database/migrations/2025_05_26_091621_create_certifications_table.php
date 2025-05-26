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
            $table->string('level')->nullable();
            $table->string('score')->nullable();
            $table->date('date');
            $table->string('link')->nullable();
            $table->foreignIdFor(Picture::class)->nullable()->constrained('pictures');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
