<?php

namespace App\Models;

use App\Enums\SubstituteQuality;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $from_ingredient_id
 * @property int $to_ingredient_id
 * @property SubstituteQuality $quality
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IngredientSubstitute extends Pivot
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
            'quality' => SubstituteQuality::class,
        ];
    }
}
