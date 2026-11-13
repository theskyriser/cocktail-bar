<?php

namespace App\Models;

use App\Enums\RecipeItemRole;
use App\Enums\SubstitutionRule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * One measured line of a recipe.
 *
 * `amount` is held in the unit of the ingredient itself, exactly as bar stock
 * is, so that the matching rule can compare the two without converting. The
 * unit is not repeated here; read it from `ingredient.default_unit`. A null
 * amount is an unmeasured line — a garnish, or a top-up to fill the glass.
 *
 * @property int $id
 * @property int $recipe_id
 * @property int $ingredient_id
 * @property int|null $amount
 * @property bool $is_optional
 * @property SubstitutionRule $sub_rule
 * @property RecipeItemRole $role
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Recipe $recipe
 * @property-read Ingredient $ingredient
 */
class RecipeIngredient extends Pivot
{
    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_optional' => 'boolean',
            'sub_rule' => SubstitutionRule::class,
            'role' => RecipeItemRole::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
