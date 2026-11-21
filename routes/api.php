<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('register', [AuthController::class, 'register']) // /api/v1/register
        ->middleware('throttle:10,1')
        ->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login'); // /api/v1/login

    // メールの認証リンク。ログインしていない端末（ブラウザ）で開くため、認証は署名で行う。
    Route::get('email/verify/{id}', [EmailVerificationController::class, 'verifyLink']) // /api/v1/email/verify/{id}
        ->middleware(['signed', 'throttle:10,1'])
        ->whereNumber('id')
        ->name('email.verify-link');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('me', [MeController::class, 'show'])->name('me');
        Route::patch('me', [MeController::class, 'update'])->name('me.update');
        Route::delete('me', [MeController::class, 'destroy'])
            ->middleware('throttle:6,1')
            ->name('me.destroy');
        Route::post('email/verify', [EmailVerificationController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('email.verify');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:3,1')
            ->name('email.resend');
        Route::put('me/password', [MeController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('me.password');
    });
});
