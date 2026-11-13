<?php

use App\Models\Bars;
use App\Models\Glass;
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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bars::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('image');
            $table->string('description');
            $table->text('instructions');
            $table->foreignIdFor(Glass::class)->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['bars_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
