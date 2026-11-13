<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->foreignId('parent_id')->nullable()->constrained('ingredients')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('kind');
            $table->string('type');
            $table->boolean('is_alcoholic');
            $table->string('default_unit');
            $table->string('path');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['created_by_user_id', 'slug']);
            $table->index('path');
            $table->index(['type', 'is_alcoholic']);
        });

        DB::statement('create unique index ingredients_curated_slug_unique on ingredients (slug) where created_by_user_id is null');
        DB::statement('create unique index ingredients_curated_path_unique on ingredients (path) where created_by_user_id is null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
