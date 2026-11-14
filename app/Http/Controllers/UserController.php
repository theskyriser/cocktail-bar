<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Show the authenticated user.
     */
    public function __invoke(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
