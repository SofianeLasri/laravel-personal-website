<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type'); // success, error, warning, info
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data for the notification
            $table->string('source')->nullable(); // Source of the notification (e.g., 'ai_provider', 'system')
            $table->string('action_url')->nullable(); // Optional action URL
            $table->string('action_label')->nullable(); // Optional action button label
            $table->boolean('is_read')->default(false);
            $table->boolean('is_persistent')->default(false); // If true, notification stays until manually dismissed
            $table->timestamp('read_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Optional expiration date
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
