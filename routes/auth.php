<?php

use App\Modules\Usuarios\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Usuarios\Http\Controllers\GoogleController;
use App\Modules\Usuarios\Http\Controllers\RecuperacionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Inicio de sesión con Google
    Route::get('auth/google', [GoogleController::class, 'redirect'])
        ->name('google.redirect');

    Route::get('auth/google/callback', [GoogleController::class, 'callback'])
        ->name('google.callback');

    // Recuperar contraseña con código enviado al correo
    Route::get('forgot-password', [RecuperacionController::class, 'solicitar'])
        ->name('password.request');

    Route::post('forgot-password', [RecuperacionController::class, 'enviar'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('recuperar/codigo', [RecuperacionController::class, 'codigo'])
        ->name('password.codigo');

    Route::post('recuperar/codigo', [RecuperacionController::class, 'verificar'])
        ->middleware('throttle:10,1')
        ->name('password.codigo.verificar');

    Route::post('recuperar/reenviar', [RecuperacionController::class, 'reenviar'])
        ->middleware('throttle:6,1')
        ->name('password.codigo.reenviar');

    Route::get('recuperar/nueva', [RecuperacionController::class, 'nueva'])
        ->name('password.nueva');

    Route::post('recuperar/nueva', [RecuperacionController::class, 'actualizar'])
        ->middleware('throttle:10,1')
        ->name('password.actualizar');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [ProfileController::class, 'actualizarClave'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
