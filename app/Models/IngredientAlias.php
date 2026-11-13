<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The alias column holds the normalised lookup key rather than anything
 * displayable, and normalising is the intake service's job alone.
 *
 * @property int $id
 * @property int $ingredient_id
 * @property string $alias
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ingredient $ingredient
 */
#[Fillable(['ingredient_id', 'alias'])]
class IngredientAlias extends Model
{
    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
