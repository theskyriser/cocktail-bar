<?php

use App\Enums\SubRule;
use App\Models\Ingredient;
use App\Models\Recipe;
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
        Schema::create('ingredient_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Recipe::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Ingredient::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('measurement_type');
            $table->string('role');
            $table->string('sub_rule')->default(SubRule::Exact->value);
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_recipes');
    }
};
