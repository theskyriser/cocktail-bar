<?php

use App\Models\AlcoholType;
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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('normalised')->unique();
            $table->boolean('is_alcoholic');
            $table->foreignIdFor(AlcoholType::class)->nullable()->constrained()->restrictOnDelete();
            $table->string('substitute_group')->nullable()->index();
            $table->string('ingredient_type')->index();
            $table->string('measurement_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
