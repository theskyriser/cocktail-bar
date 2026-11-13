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
        Schema::create('recipe_ingredient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->string('sub_rule')->default('exact');
            $table->string('role');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id']);
            $table->index(['ingredient_id', 'is_optional']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredient');
    }
};
