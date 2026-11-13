<?php

use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;

it('tags a recipe and reads it back from both sides', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['name' => 'Negroni']);
    $tag = Tag::factory()->ownedBy($user)->create(['name' => 'bitter']);

    $recipe->tags()->attach($tag);

    expect($recipe->tags->pluck('name')->all())->toBe(['bitter'])
        ->and($tag->recipes->pluck('name')->all())->toBe(['Negroni']);
});

it('stamps the join row when a tag is applied', function () {
    $recipe = Recipe::factory()->create();
    $tag = Tag::factory()->create();

    $recipe->tags()->attach($tag);

    expect(RecipeTag::sole()->created_at)->not->toBeNull();
});

it('lets two users each hold a tag of the same name', function () {
    $first = Tag::factory()->ownedBy(User::factory()->create())->create(['name' => 'sour']);
    $second = Tag::factory()->ownedBy(User::factory()->create())->create(['name' => 'sour']);

    expect($first->name)->toBe($second->name)
        ->and($first->user_id)->not->toBe($second->user_id);
});

it('refuses the same tag name twice for one user', function () {
    $user = User::factory()->create();
    Tag::factory()->ownedBy($user)->create(['name' => 'sour']);

    expect(fn () => Tag::factory()->ownedBy($user)->create(['name' => 'sour']))
        ->toThrow(QueryException::class);
});

it('refuses to apply the same tag to a recipe twice', function () {
    $recipe = Recipe::factory()->create();
    $tag = Tag::factory()->create();

    $recipe->tags()->attach($tag);

    expect(fn () => $recipe->tags()->attach($tag))->toThrow(QueryException::class);
});

it('drops the join rows when a recipe or a tag goes', function () {
    $recipe = Recipe::factory()->create();
    $tag = Tag::factory()->create();
    $recipe->tags()->attach($tag);

    $recipe->delete();
    expect(RecipeTag::count())->toBe(0);

    $other = Recipe::factory()->create();
    $other->tags()->attach($tag);
    $tag->delete();

    expect(RecipeTag::count())->toBe(0)
        ->and($other->fresh())->not->toBeNull();
});

it('drops a users tags when the user goes', function () {
    $user = User::factory()->create();
    Tag::factory()->ownedBy($user)->create();

    $user->delete();

    expect(Tag::count())->toBe(0);
});
