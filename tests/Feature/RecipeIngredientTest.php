<?php

use App\Enums\IngredientKind;
use App\Enums\IngredientType;
use App\Enums\MeasurementUnit;
use App\Enums\RecipeItemRole;
use App\Enums\SubstitutionRule;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\QueryException;

function ingredient(string $name, MeasurementUnit $unit = MeasurementUnit::Millilitre): Ingredient
{
    return Ingredient::create([
        'slug' => str($name)->slug()->value(),
        'name' => $name,
        'kind' => IngredientKind::Product,
        'type' => IngredientType::Spirit,
        'is_alcoholic' => true,
        'default_unit' => $unit,
    ]);
}

it('builds a recipe out of ordered, measured lines', function () {
    $recipe = Recipe::factory()->create(['name' => 'Negroni']);

    $gin = ingredient('Gin');
    $campari = ingredient('Campari');
    $vermouth = ingredient('Sweet Vermouth');

    $recipe->items()->createMany([
        ['ingredient_id' => $gin->id, 'amount' => 30, 'role' => RecipeItemRole::BaseSpirit, 'sub_rule' => SubstitutionRule::AnyInGroup, 'position' => 1],
        ['ingredient_id' => $campari->id, 'amount' => 30, 'role' => RecipeItemRole::Bitters, 'position' => 2],
        ['ingredient_id' => $vermouth->id, 'amount' => 30, 'role' => RecipeItemRole::Modifier, 'position' => 3],
    ]);

    expect($recipe->items()->pluck('amount')->all())->toBe([30, 30, 30])
        ->and($recipe->ingredients->pluck('name')->all())->toBe(['Gin', 'Campari', 'Sweet Vermouth']);

    $base = $recipe->items()->first();

    expect($base->role)->toBe(RecipeItemRole::BaseSpirit)
        ->and($base->sub_rule)->toBe(SubstitutionRule::AnyInGroup)
        ->and($base->is_optional)->toBeFalse();
});

it('defaults a line to an exact, required match', function () {
    $recipe = Recipe::factory()->create();

    $line = $recipe->items()->create([
        'ingredient_id' => ingredient('Rye')->id,
        'amount' => 60,
        'role' => RecipeItemRole::BaseSpirit,
        'position' => 1,
    ]);

    expect($line->fresh()->sub_rule)->toBe(SubstitutionRule::Exact)
        ->and($line->fresh()->is_optional)->toBeFalse();
});

it('allows an unmeasured optional line for a garnish', function () {
    $recipe = Recipe::factory()->create();

    $line = $recipe->items()->create([
        'ingredient_id' => ingredient('Orange Twist', MeasurementUnit::Count)->id,
        'amount' => null,
        'is_optional' => true,
        'role' => RecipeItemRole::Garnish,
        'position' => 9,
    ]);

    expect($line->fresh()->amount)->toBeNull()
        ->and($line->fresh()->is_optional)->toBeTrue();
});

it('refuses the same ingredient twice in one recipe', function () {
    $recipe = Recipe::factory()->create();
    $gin = ingredient('Gin');

    $recipe->items()->create([
        'ingredient_id' => $gin->id, 'amount' => 30, 'role' => RecipeItemRole::BaseSpirit, 'position' => 1,
    ]);

    expect(fn () => $recipe->items()->create([
        'ingredient_id' => $gin->id, 'amount' => 15, 'role' => RecipeItemRole::Modifier, 'position' => 2,
    ]))->toThrow(QueryException::class);
});

it('discards the lines when the recipe goes', function () {
    $recipe = Recipe::factory()->create();
    $recipe->items()->create([
        'ingredient_id' => ingredient('Gin')->id, 'amount' => 30, 'role' => RecipeItemRole::BaseSpirit, 'position' => 1,
    ]);

    $recipe->delete();

    expect(RecipeIngredient::count())->toBe(0);
});

it('reads back from the ingredient which recipes use it', function () {
    $gin = ingredient('Gin');

    foreach (['Negroni', 'Martini'] as $position => $name) {
        Recipe::factory()->create(['name' => $name])->items()->create([
            'ingredient_id' => $gin->id, 'amount' => 30, 'role' => RecipeItemRole::BaseSpirit, 'position' => 1,
        ]);
    }

    expect($gin->recipes->pluck('name')->all())->toEqualCanonicalizing(['Negroni', 'Martini'])
        ->and($gin->recipes->first()->pivot->amount)->toBe(30);
});
