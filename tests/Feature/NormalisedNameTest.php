<?php

use Illuminate\Database\UniqueConstraintViolationException;

it('stores a lookup key when an ingredient is saved', function () {
    expect(makeIngredient("Gordon's Gin")->fresh()->normalised)->toBe('gordons gin');
});

it('folds accents into the lookup key', function () {
    expect(makeIngredient('Crème de Cassis')->fresh()->normalised)->toBe('creme de cassis');
});

it('keeps the name as typed', function () {
    expect(makeIngredient("Gordon's Gin")->fresh()->name)->toBe("Gordon's Gin");
});

it('rejects two ingredients whose names differ only by punctuation', function () {
    makeIngredient("Gordon's Gin");

    expect(fn () => makeIngredient('Gordons Gin'))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects two ingredients whose names differ only by case', function () {
    makeIngredient('Rittenhouse');

    expect(fn () => makeIngredient('RITTENHOUSE'))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('updates the lookup key when the name changes', function () {
    $ingredient = makeIngredient('Campari');
    $ingredient->name = 'Aperol';
    $ingredient->save();

    expect($ingredient->fresh()->normalised)->toBe('aperol');
});
