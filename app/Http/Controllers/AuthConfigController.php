<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

class AuthConfigController extends Controller
{
    /**
     * Show the auth configuration the client cannot derive for itself.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'password_rules' => Password::defaults()->toPasswordRulesString(),
            'can_reset_password' => Features::enabled(Features::resetPasswords()),
            'registration_enabled' => Features::enabled(Features::registration()),
            'must_verify_email' => Features::enabled(Features::emailVerification()),
        ]);
    }
}
