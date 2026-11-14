<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

function verificationUrl(User $user, ?int $id = null, ?string $email = null): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $id ?? $user->id, 'hash' => sha1($email ?? $user->email)],
    );
}

test('unverified users are refused a verified-only endpoint', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->deleteJson(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect($user->fresh())->not->toBeNull();
});

test('email can be verified', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson(verificationUrl($user))
        ->assertNoContent();

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('verifying from a mail client lands back in the SPA', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrl($user))
        ->assertRedirect(config('fortify.redirects.email-verification').'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with invalid hash', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->getJson(verificationUrl($user, email: 'wrong-email'));

    Event::assertNotDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->getJson(verificationUrl($user, id: 123));

    Event::assertNotDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an already verified user does not fire the event again', function () {
    Event::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(verificationUrl($user))
        ->assertNoContent();

    Event::assertNotDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
