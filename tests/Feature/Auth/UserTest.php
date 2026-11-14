<?php

use App\Models\User;

test('the authenticated user is returned as a bare object', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('user'))
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', $user->name)
        ->assertJsonPath('email', $user->email);

    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'id',
        'name',
        'email',
        'email_verified_at',
        'created_at',
        'updated_at',
    ]);
});

test('timestamps are serialised as ISO 8601', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('user'))
        ->assertJsonPath('created_at', $user->created_at->toJSON())
        ->assertJsonPath('email_verified_at', $user->email_verified_at->toJSON());
});

test('guests get a 401 rather than a redirect', function () {
    $this->getJson(route('user'))
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});
