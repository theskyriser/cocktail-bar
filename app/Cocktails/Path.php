<?php

namespace App\Cocktails;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Stringable;

final readonly class Path implements Castable, Stringable
{
    public function __construct(public string $value) {}

    public static function root(string $slug): self
    {
        return new self($slug);
    }

    public function append(string $slug): self
    {
        return new self($this->value.'.'.$slug);
    }

    /**
     * Whether this path is the given path or one of its ancestors, which is
     * how a recipe line for "gin" is satisfied by a bottle of Gin Mare.
     */
    public function covers(self $other): bool
    {
        return $this->value === $other->value
            || str_starts_with($other->value, $this->value.'.');
    }

    /**
     * A SQL LIKE pattern matching every descendant but not this path itself.
     * Slugs must never contain an underscore, which LIKE treats as a wildcard.
     */
    public function descendantPattern(): string
    {
        return $this->value.'.%';
    }

    public function depth(): int
    {
        return substr_count($this->value, '.') + 1;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return CastsAttributes<Path, Path|string>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new PathCast;
    }
}
