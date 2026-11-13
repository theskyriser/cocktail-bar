<?php

namespace App\Models;

use App\Concerns\HasNormalisedName;
use Database\Factories\AlcoholFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Alcohol extends Model
{
    /** @use HasFactory<AlcoholFactory> */
    use HasFactory;

    use HasNormalisedName;

    /**
     * @return MorphMany<Alias, $this>
     */
    public function aliases(): MorphMany
    {
        return $this->morphMany(Alias::class, 'aliasable');
    }
}
