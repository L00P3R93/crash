<?php

use App\Http\Controllers\Api\AviatorBetController;
use App\Http\Controllers\Api\AviatorConfigController;
use App\Http\Controllers\Api\AviatorRoundController;
use App\Http\Controllers\Api\PlayerAuthController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [PlayerAuthController::class, 'login']);

Route::get('aviator/config', [AviatorConfigController::class, 'show']);
Route::get('aviator/round', [AviatorRoundController::class, 'current']);
Route::get('aviator/round/players', [AviatorRoundController::class, 'players']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [PlayerAuthController::class, 'logout']);
    Route::get('auth/me', [PlayerAuthController::class, 'me']);

    Route::post('aviator/bets', [AviatorBetController::class, 'store']);
    Route::get('aviator/bets/recent', [AviatorBetController::class, 'recent']);
    Route::get('aviator/bets/active', [AviatorBetController::class, 'active']);
    Route::get('aviator/bets/{bet}', [AviatorBetController::class, 'show']);
    Route::post('aviator/bets/{bet}/cashout', [AviatorBetController::class, 'cashOut']);

    Route::post('wallet/topup', [WalletController::class, 'topup']);
    Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);
});
