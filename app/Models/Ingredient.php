<?php

namespace App\Models;

use App\Enums\IngredientType;
use App\Enums\MeasurementType;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ingredient_type' => IngredientType::class,
            'measurement_type' => MeasurementType::class,
        ];
    }
}
