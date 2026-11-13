<?php

namespace App\Models;

use App\Concerns\HasNormalisedName;
use App\Enums\IngredientType;
use App\Enums\MeasurementType;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    use HasNormalisedName;

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

    /**
     * @return MorphMany<Alias, $this>
     */
    public function aliases(): MorphMany
    {
        return $this->morphMany(Alias::class, 'aliasable');
    }
}
