<?php

use App\Enums\AliasSource;
use App\Intake\NameNormaliser;
use App\Models\Alcohol;
use App\Models\Alias;
use App\Models\Ingredient;
use Illuminate\Database\UniqueConstraintViolationException;

it('resolves many names to one ingredient', function () {
    $vermouth = makeIngredient('Sweet Vermouth');
    aliasFor($vermouth, 'Red Vermouth');
    aliasFor($vermouth, 'Rosso');
    aliasFor($vermouth, 'Vermouth Rosso');

    $ids = Alias::query()
        ->whereIn('normalised', ['red vermouth', 'rosso', 'vermouth rosso'])
        ->pluck('aliasable_id')
        ->unique();

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->toBe($vermouth->id);
});

it('resolves rosso to sweet vermouth, which scoring never could', function () {
    $vermouth = makeIngredient('Sweet Vermouth');
    aliasFor($vermouth, 'Rosso');

    $resolved = Alias::query()
        ->where('normalised', NameNormaliser::normalise('ROSSO'))
        ->sole()
        ->aliasable;

    expect($resolved)->toBeInstanceOf(Ingredient::class)
        ->and($resolved->name)->toBe('Sweet Vermouth');
});

it('normalises the alias text into the lookup key', function () {
    aliasFor(makeIngredient('Creme de Cassis'), 'Crème   de Cassis');

    expect(Alias::query()->sole())
        ->alias->toBe('Crème   de Cassis')
        ->normalised->toBe('creme de cassis');
});

it('refuses an alias key that already points somewhere', function () {
    aliasFor(makeIngredient('Sweet Vermouth'), 'Rosso');
    $dry = makeIngredient('Dry Vermouth');

    expect(fn () => aliasFor($dry, 'rosso'))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('aliases an alcohol as well as an ingredient', function () {
    $rittenhouse = makeAlcohol('Rittenhouse');
    aliasFor($rittenhouse, 'Rittenhouse Rye');

    expect($rittenhouse->aliases)->toHaveCount(1)
        ->and(Alias::query()->where('normalised', 'rittenhouse rye')->sole()->aliasable)
        ->toBeInstanceOf(Alcohol::class);
});

it('reaches every alias from the ingredient', function () {
    $vermouth = makeIngredient('Sweet Vermouth');
    aliasFor($vermouth, 'Red Vermouth');
    aliasFor($vermouth, 'Rosso', AliasSource::User);

    expect($vermouth->aliases()->pluck('normalised')->sort()->values()->all())
        ->toBe(['red vermouth', 'rosso']);
});

it('records where an alias came from', function () {
    $vermouth = makeIngredient('Sweet Vermouth');
    aliasFor($vermouth, 'Rosso', AliasSource::User);

    expect(Alias::query()->sole()->source)->toBe(AliasSource::User);
});
