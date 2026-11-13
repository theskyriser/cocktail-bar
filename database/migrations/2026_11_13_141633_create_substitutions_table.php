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
            $table->morphs('original');
            $table->morphs('substitution');
            $table->string('quality');
            $table->timestamps();

            $table->unique(
                ['original_type', 'original_id', 'substitution_type', 'substitution_id'],
                'substitutions_pair_unique'
            );
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
