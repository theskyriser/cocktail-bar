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
        Schema::create('ingredient_substitute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('to_ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('quality');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['from_ingredient_id', 'to_ingredient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_substitute');
    }
};
