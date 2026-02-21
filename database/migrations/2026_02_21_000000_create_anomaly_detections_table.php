<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomaly_detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // e.g., 'high_rental_frequency', 'inconsistent_ratings'
            $table->decimal('score', 5, 4); // Anomaly score (0-1)
            $table->json('details')->nullable(); // Additional details
            $table->string('status')->default('pending'); // pending, resolved, ignored
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_detections');
    }
};