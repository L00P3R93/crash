<?php

use App\Http\Controllers\Webhooks\MpesaB2cResultController;
use App\Http\Controllers\Webhooks\MpesaStkCallbackController;
use App\Http\Middleware\VerifyMpesaIp;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/mpesa/stk-callback', [MpesaStkCallbackController::class, 'handle'])
    ->middleware(['throttle:mpesa-webhooks', VerifyMpesaIp::class])
    ->name('mpesa.stk.callback');

Route::post('/webhooks/mpesa/b2c-result', [MpesaB2cResultController::class, 'handle'])
    ->middleware(['throttle:mpesa-webhooks', VerifyMpesaIp::class])
    ->name('mpesa.b2c.result');
