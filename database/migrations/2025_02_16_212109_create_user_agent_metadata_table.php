<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_agent_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(UserAgent::class);
            $table->boolean('is_bot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_agent_metadata');
    }
};
