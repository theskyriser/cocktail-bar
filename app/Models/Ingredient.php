<?php

namespace App\Models;

use App\Concerns\HasNormalisedName;
use App\Enums\IngredientType;
use App\Enums\MeasurementType;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'is_alcoholic' => 'boolean',
            'ingredient_type' => IngredientType::class,
            'measurement_type' => MeasurementType::class,
        ];
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function alcoholic(Builder $query): void
    {
        $query->where('is_alcoholic', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function nonAlcoholic(Builder $query): void
    {
        $query->where('is_alcoholic', false);
    }

    /**
     * @return BelongsTo<AlcoholType, $this>
     */
    public function alcoholType(): BelongsTo
    {
        return $this->belongsTo(AlcoholType::class);
    }

    /**
     * @return HasMany<Alias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(Alias::class);
    }
}
