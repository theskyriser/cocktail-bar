<?php

namespace App\Concerns;

use App\Intake\NameNormaliser;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasNormalisedName
{
    /**
     * @return Attribute<never, string>
     */
    protected function name(): Attribute
    {
        return Attribute::set(fn (string $value): array => [
            'name' => $value,
            'normalised' => NameNormaliser::normalise($value),
        ]);
    }
}
