<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\Response;

class SecurityController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): Response
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        /*
         * Sanctum's AuthenticateSession middleware signs a session out once the
         * password hash it stored stops matching, which is what logs other
         * devices out. Refresh it here so changing your own password does not
         * sign you out of the session that just changed it. The key is keyed on
         * the session guard, not the route's "sanctum" guard.
         */
        $request->session()->put(
            'password_hash_'.config('fortify.guard'),
            $request->user()->getAuthPassword(),
        );

        return response()->noContent();
    }
}
