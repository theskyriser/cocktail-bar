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
        Schema::create('substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('substitution_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('quality');
            $table->timestamps();

            $table->unique(['original_id', 'substitution_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('substitutions');
    }
};
