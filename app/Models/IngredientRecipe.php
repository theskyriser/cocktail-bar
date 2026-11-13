<?php

namespace App\Models;

use App\Enums\MeasurementType;
use App\Enums\RecipeRole;
use App\Enums\SubRule;
use Database\Factories\IngredientRecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IngredientRecipe extends Model
{
    /** @use HasFactory<IngredientRecipeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'measurement_type' => MeasurementType::class,
            'role' => RecipeRole::class,
            'sub_rule' => SubRule::class,
        ];
    }
}
