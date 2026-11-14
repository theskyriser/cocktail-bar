<?php

use Illuminate\Validation\Rules\Password;

test('auth config is readable by guests', function () {
    $this->getJson(route('auth.config'))
        ->assertOk()
        ->assertExactJson([
            'password_rules' => Password::defaults()->toPasswordRulesString(),
            'can_reset_password' => true,
            'registration_enabled' => true,
            'must_verify_email' => true,
        ]);
});

test('auth config reports disabled features', function () {
    config(['fortify.features' => []]);

    $this->getJson(route('auth.config'))
        ->assertOk()
        ->assertJson([
            'can_reset_password' => false,
            'registration_enabled' => false,
            'must_verify_email' => false,
        ]);
});
