<?php

use App\Enums\AlcoholParentType;
use App\Enums\IngredientType;
use App\Enums\MeasurementType;
use App\Models\Alcohol;
use App\Models\AlcoholType;
use App\Models\Ingredient;
use Illuminate\Database\UniqueConstraintViolationException;

function makeIngredient(string $name): Ingredient
{
    $ingredient = new Ingredient;
    $ingredient->name = $name;
    $ingredient->ingredient_type = IngredientType::Spirit;
    $ingredient->measurement_type = MeasurementType::Millilitres;
    $ingredient->save();

    return $ingredient;
}

function makeAlcohol(string $name): Alcohol
{
    $type = new AlcoholType;
    $type->name = $name.' type';
    $type->parent_type = AlcoholParentType::Gin;
    $type->save();

    $alcohol = new Alcohol;
    $alcohol->name = $name;
    $alcohol->alcohol_type_id = $type->id;
    $alcohol->save();

    return $alcohol;
}

it('stores a lookup key when an ingredient is saved', function () {
    expect(makeIngredient("Gordon's Gin")->fresh()->normalised)->toBe('gordons gin');
});

it('stores a lookup key when an alcohol is saved', function () {
    expect(makeAlcohol('Crème de Cassis')->fresh()->normalised)->toBe('creme de cassis');
});

it('keeps the name as typed', function () {
    expect(makeIngredient("Gordon's Gin")->fresh()->name)->toBe("Gordon's Gin");
});

it('rejects two ingredients whose names differ only by punctuation', function () {
    makeIngredient("Gordon's Gin");

    expect(fn () => makeIngredient('Gordons Gin'))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects two alcohols whose names differ only by case', function () {
    makeAlcohol('Rittenhouse');

    expect(fn () => makeAlcohol('RITTENHOUSE'))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('updates the lookup key when the name changes', function () {
    $ingredient = makeIngredient('Campari');
    $ingredient->name = 'Aperol';
    $ingredient->save();

    expect($ingredient->fresh()->normalised)->toBe('aperol');
});
