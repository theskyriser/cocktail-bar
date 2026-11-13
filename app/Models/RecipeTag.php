<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The join row between a recipe and one of its tags. It carries no data of its
 * own, so the relations do not hydrate it; it exists for the rows that need
 * addressing directly.
 *
 * @property int $id
 * @property int $recipe_id
 * @property int $tag_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Recipe $recipe
 * @property-read Tag $tag
 */
class RecipeTag extends Pivot
{
    public $incrementing = true;

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<Tag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
