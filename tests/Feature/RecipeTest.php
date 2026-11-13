<?php

use App\Models\Glass;
use App\Models\Recipe;
use App\Models\User;

it('creates a recipe with a glass and a creator', function () {
    $user = User::factory()->create();
    $glass = Glass::factory()->create();

    $recipe = Recipe::factory()->createdBy($user)->servedIn($glass)->create([
        'name' => 'Negroni',
        'description' => 'Equal parts, stirred.',
        'method' => 'Stir with ice. Strain over a large cube.',
    ]);

    expect($recipe->fresh()->name)->toBe('Negroni')
        ->and($recipe->glass->id)->toBe($glass->id)
        ->and($recipe->creator->id)->toBe($user->id);
});

it('treats a null creator as curated and shows it to everyone', function () {
    $mine = User::factory()->create();
    $other = User::factory()->create();

    Recipe::factory()->create(['name' => 'Classic Daiquiri']);
    Recipe::factory()->createdBy($mine)->create(['name' => 'My Own']);
    Recipe::factory()->createdBy($other)->create(['name' => 'Someone Elses']);

    expect(Recipe::curated()->pluck('name')->all())->toBe(['Classic Daiquiri'])
        ->and(Recipe::visibleTo($mine)->pluck('name')->all())
        ->toEqualCanonicalizing(['Classic Daiquiri', 'My Own'])
        ->and(Recipe::visibleTo(null)->pluck('name')->all())->toBe(['Classic Daiquiri']);
});

it('nulls the glass but keeps the recipe when a glass is deleted', function () {
    $glass = Glass::factory()->create();
    $recipe = Recipe::factory()->servedIn($glass)->create();

    $glass->delete();

    expect($recipe->fresh())->not->toBeNull()
        ->and($recipe->fresh()->glass_id)->toBeNull();
});
