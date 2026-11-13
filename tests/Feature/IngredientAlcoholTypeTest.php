<?php

use App\Enums\AlcoholParentType;
use Illuminate\Database\QueryException;

it('lets an ingredient carry no alcohol type', function () {
    expect(makeIngredient('Lime Juice', isAlcoholic: false)->alcohol_type_id)->toBeNull();
});

it('reaches the alcohol type from the ingredient', function () {
    $rye = makeAlcoholType('Rye');

    expect(makeIngredient('Rittenhouse', $rye)->alcoholType)
        ->name->toBe('Rye')
        ->parent_type->toBe(AlcoholParentType::Whiskey);
});

it('refuses an alcohol type that does not exist', function () {
    expect(function () {
        $ingredient = makeIngredient('Rittenhouse');
        $ingredient->alcohol_type_id = 99999;
        $ingredient->save();
    })->toThrow(QueryException::class);
});

it('refuses to delete an alcohol type still used by an ingredient', function () {
    $rye = makeAlcoholType('Rye');
    makeIngredient('Rittenhouse', $rye);

    expect(fn () => $rye->delete())->toThrow(QueryException::class);
});
