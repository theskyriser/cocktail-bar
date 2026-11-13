<?php

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
        Schema::create('bar_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Bars::class)->constrained()->cascadeOnDelete();
            $table->morphs('item');
            $table->integer('amount');
            $table->unsignedInteger('bottle_size')->nullable();
            $table->timestamps();

            $table->unique(['bars_id', 'item_type', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_stocks');
    }
};
