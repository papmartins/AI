<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('movie_id')->constrained()->onDelete('cascade');
            $table->date('rented_at');
            $table->date('due_date');
            $table->boolean('returned')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('rentals'); }
};
