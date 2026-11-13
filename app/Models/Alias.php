<?php

namespace App\Models;

use App\Enums\AliasSource;
use App\Intake\NameNormaliser;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alias extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AliasSource::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function aliasable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return Attribute<never, string>
     */
    protected function alias(): Attribute
    {
        return Attribute::set(fn (string $value): array => [
            'alias' => $value,
            'normalised' => NameNormaliser::normalise($value),
        ]);
    }
}
