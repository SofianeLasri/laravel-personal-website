<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('model', 50);
            $table->string('endpoint', 255);
            $table->enum('status', ['success', 'error', 'timeout', 'fallback']);
            $table->integer('http_status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->text('system_prompt');
            $table->text('user_prompt');
            $table->json('response')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->decimal('response_time', 8, 3); // in seconds
            $table->decimal('estimated_cost', 10, 6)->nullable(); // in USD
            $table->string('fallback_provider', 20)->nullable();
            $table->json('metadata')->nullable(); // Additional info
            $table->boolean('cached')->default(false);
            $table->timestamps();

            // Indexes for frequent queries
            $table->index(['provider', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
