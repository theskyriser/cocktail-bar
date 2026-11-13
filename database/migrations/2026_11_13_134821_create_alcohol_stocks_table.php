<?php

use App\Models\Alcohol;
use App\Models\Bars;
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
        Schema::create('alcohol_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bars::class)->constrained();
            $table->foreignIdFor(Alcohol::class)->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alcohol_stocks');
    }
};
