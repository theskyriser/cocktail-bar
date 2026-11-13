<?php

use App\Enums\IngredientKind;
use App\Enums\IngredientType;
use App\Enums\MeasurementUnit;
use App\Enums\SubstituteQuality;
use App\Models\Ingredient;
use App\Models\IngredientSubstitute;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function ingredientNamed(string $name): Ingredient
{
    return Ingredient::create([
        'slug' => str($name)->slug()->value(),
        'name' => $name,
        'kind' => IngredientKind::Product,
        'type' => IngredientType::Spirit,
        'is_alcoholic' => true,
        'default_unit' => MeasurementUnit::Millilitre,
    ]);
}

it('resolves the pivot model onto the table the migration creates', function () {
    $table = (new IngredientSubstitute)->getTable();

    expect($table)->toBe('ingredient_substitute')
        ->and(Schema::hasTable($table))->toBeTrue()
        ->and(IngredientSubstitute::count())->toBe(0);
});

it('points both substitution relations at that same table', function () {
    $ingredient = ingredientNamed('Rye');

    expect($ingredient->substitutes()->getTable())->toBe((new IngredientSubstitute)->getTable())
        ->and($ingredient->substituteFor()->getTable())->toBe((new IngredientSubstitute)->getTable());
});

it('reads a substitution written through the relation back off the model', function () {
    $rye = ingredientNamed('Rye');
    $bourbon = ingredientNamed('Bourbon');

    $rye->substitutes()->attach($bourbon, [
        'quality' => SubstituteQuality::Acceptable,
        'note' => 'Sweeter, less spice.',
    ]);

    $substitution = IngredientSubstitute::query()
        ->where('from_ingredient_id', $rye->id)
        ->sole();

    expect($substitution->to_ingredient_id)->toBe($bourbon->id)
        ->and($substitution->quality)->toBe(SubstituteQuality::Acceptable)
        ->and($substitution->note)->toBe('Sweeter, less spice.');
});

it('reads a substitution written through the model back off the relation', function () {
    $cordial = ingredientNamed('Lime Cordial');
    $juice = ingredientNamed('Lime Juice');

    IngredientSubstitute::create([
        'from_ingredient_id' => $cordial->id,
        'to_ingredient_id' => $juice->id,
        'quality' => SubstituteQuality::Poor,
    ]);

    expect($cordial->substitutes->pluck('name')->all())->toBe(['Lime Juice'])
        ->and($juice->substituteFor->pluck('name')->all())->toBe(['Lime Cordial'])
        ->and($cordial->substitutes->first()->pivot->quality)->toBe(SubstituteQuality::Poor);
});

it('refuses the same substitution twice', function () {
    $rye = ingredientNamed('Rye');
    $bourbon = ingredientNamed('Bourbon');

    IngredientSubstitute::create([
        'from_ingredient_id' => $rye->id,
        'to_ingredient_id' => $bourbon->id,
        'quality' => SubstituteQuality::Acceptable,
    ]);

    expect(fn () => IngredientSubstitute::create([
        'from_ingredient_id' => $rye->id,
        'to_ingredient_id' => $bourbon->id,
        'quality' => SubstituteQuality::Good,
    ]))->toThrow(QueryException::class);
});

it('discards the substitution when either ingredient goes', function () {
    $rye = ingredientNamed('Rye');
    $bourbon = ingredientNamed('Bourbon');

    $rye->substitutes()->attach($bourbon, ['quality' => SubstituteQuality::Acceptable]);

    $bourbon->delete();

    expect(IngredientSubstitute::count())->toBe(0);
});
