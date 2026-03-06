<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name_en'); // English name
            $table->string('name_pt'); // Portuguese name
            $table->string('name_es'); // Spanish name
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('genres'); }
};
