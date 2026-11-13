<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Amounts are held in the unit of the ingredient itself, so `amount` and
 * `bottle_size` mean millilitres for a bottle, whole fruit for a lime and
 * dashes for bitters. The unit is not repeated here; read it from
 * `ingredient.default_unit`.
 *
 * @property int $id
 * @property int $bar_id
 * @property int $ingredient_id
 * @property int $amount
 * @property int|null $bottle_size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Bar $bar
 * @property-read Ingredient $ingredient
 */
#[Table('bar_stock')]
#[Fillable(['bar_id', 'ingredient_id', 'amount', 'bottle_size'])]
class BarStock extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'bottle_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Bar, $this>
     */
    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
