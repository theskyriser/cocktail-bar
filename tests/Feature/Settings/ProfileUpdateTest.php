<?php

use App\Models\User;

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])
        ->assertNoContent();

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email is stored in lowercase so the user can still log in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'Test@Example.com',
        ])
        ->assertNoContent();

    expect($user->refresh()->email)->toBe('test@example.com');

    $this->postJson(route('logout'))->assertNoContent();

    $this->postJson(route('login.store'), [
        'email' => 'Test@Example.com',
        'password' => 'password',
    ])->assertOk();

    $this->assertAuthenticatedAs($user);
});

test('email must be unique regardless of case', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'Taken@Example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])
        ->assertNoContent();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->deleteJson(route('profile.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect($user->fresh())->toBeNull();

    $this->assertGuest(config('fortify.guard'));

    /*
     * One container is reused across requests here, so the guard that already
     * resolved this user would answer from memory. Drop it to see what a
     * genuinely new request sees.
     */
    app('auth')->forgetGuards();

    $this->getJson(route('user'))->assertUnauthorized();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect($user->fresh())->not->toBeNull();
});

test('guests cannot update a profile', function () {
    $this->patchJson(route('profile.update'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ])->assertUnauthorized();
});
