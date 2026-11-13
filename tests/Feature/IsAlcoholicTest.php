<?php

use App\Enums\IngredientType;
use App\Enums\MeasurementType;
use App\Models\Ingredient;
use Illuminate\Database\QueryException;

it('separates the alcohol from everything else', function () {
    makeIngredient('Rittenhouse');
    makeIngredient('Campari');
    makeIngredient('Lime Juice', isAlcoholic: false);
    makeIngredient('Simple Syrup', isAlcoholic: false);

    expect(Ingredient::query()->alcoholic()->pluck('name')->sort()->values()->all())
        ->toBe(['Campari', 'Rittenhouse'])
        ->and(Ingredient::query()->nonAlcoholic()->pluck('name')->sort()->values()->all())
        ->toBe(['Lime Juice', 'Simple Syrup']);
});

it('represents a zero-proof spirit, which its category cannot', function () {
    $seedlip = makeIngredient('Seedlip Garden 108', isAlcoholic: false);

    expect($seedlip->fresh())
        ->ingredient_type->toBe(IngredientType::Spirit)
        ->is_alcoholic->toBeFalse();
});

it('reads back as a boolean', function () {
    expect(makeIngredient('Rittenhouse')->fresh()->is_alcoholic)->toBeTrue();
});

it('refuses an ingredient that does not say whether it is alcoholic', function () {
    expect(function () {
        $ingredient = new Ingredient;
        $ingredient->name = 'Mystery Bottle';
        $ingredient->ingredient_type = IngredientType::Spirit;
        $ingredient->measurement_type = MeasurementType::Millilitres;
        $ingredient->save();
    })->toThrow(QueryException::class);
});
