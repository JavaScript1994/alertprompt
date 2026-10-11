<?php

use App\Http\Controllers\EmailChangeController;
use Illuminate\Support\Facades\Route;

// Confirmación del cambio de correo de inicio de sesión (enlace firmado, 24 h).
Route::get('/email-change/{user}/{hash}', [EmailChangeController::class, 'confirm'])
    ->whereNumber('user')
    ->middleware(['signed', 'throttle:10,1'])
    ->name('email-change.confirm');

Route::view('/{any}', 'app')->where('any', '.*');
