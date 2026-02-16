<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('year');
            $table->foreignId('genre_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 8, 2);
            $table->integer('stock')->default(1);
            $table->string('poster')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('movies'); }
};
