<?php

use App\Http\Controllers\Ussd\AfricasTalkingUssdController;
use App\Http\Middleware\VerifyAfricasTalkingRequest;
use Illuminate\Support\Facades\Route;

Route::post('/ussd/aviator', [AfricasTalkingUssdController::class, 'handle'])
    ->middleware(['throttle:ussd', VerifyAfricasTalkingRequest::class])
    ->name('ussd.aviator');
