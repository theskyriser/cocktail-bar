<?php

namespace Database\Factories;

use App\Models\Glass;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'glass_id' => null,
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'method' => $this->faker->sentences(3, true),
            'created_by_user_id' => null,
        ];
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_by_user_id' => $user->id,
        ]);
    }

    public function servedIn(Glass $glass): static
    {
        return $this->state(fn (array $attributes): array => [
            'glass_id' => $glass->id,
        ]);
    }
}
