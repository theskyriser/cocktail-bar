<?php

use App\Http\Controllers\AuthConfigController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('auth/config', AuthConfigController::class)->name('auth.config');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('user', UserController::class)->name('user');

    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::middleware('verified')->group(function (): void {
        Route::delete('settings/profile', [ProfileController::class, 'destroy'])
            ->name('profile.destroy');

        Route::put('settings/password', [SecurityController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('user-password.update');
    });
});
