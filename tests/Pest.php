<?php

use App\Enums\AlcoholParentType;
use App\Enums\AliasSource;
use App\Enums\IngredientType;
use App\Enums\MeasurementType;
use App\Models\Alcohol;
use App\Models\AlcoholType;
use App\Models\Alias;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

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

function aliasFor(Model $owner, string $alias, AliasSource $source = AliasSource::Seed): Alias
{
    $row = new Alias;
    $row->aliasable_type = $owner::class;
    $row->aliasable_id = $owner->id;
    $row->alias = $alias;
    $row->source = $source;
    $row->save();

    return $row;
}
