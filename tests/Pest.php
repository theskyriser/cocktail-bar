<?php

use App\Enums\AlcoholParentType;
use App\Enums\AliasSource;
use App\Enums\IngredientType;
use App\Enums\MeasurementType;
use App\Models\AlcoholType;
use App\Models\Alias;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function makeIngredient(string $name, ?AlcoholType $alcoholType = null, bool $isAlcoholic = true): Ingredient
{
    $ingredient = new Ingredient;
    $ingredient->name = $name;
    $ingredient->is_alcoholic = $isAlcoholic;
    $ingredient->ingredient_type = IngredientType::Spirit;
    $ingredient->measurement_type = MeasurementType::Millilitres;
    $ingredient->alcohol_type_id = $alcoholType?->id;
    $ingredient->save();

    return $ingredient;
}

function makeAlcoholType(string $name): AlcoholType
{
    $type = new AlcoholType;
    $type->name = $name;
    $type->parent_type = AlcoholParentType::Whiskey;
    $type->save();

    return $type;
}

function aliasFor(Ingredient $ingredient, string $alias, AliasSource $source = AliasSource::Seed): Alias
{
    $row = new Alias;
    $row->ingredient_id = $ingredient->id;
    $row->alias = $alias;
    $row->source = $source;
    $row->save();

    return $row;
}
