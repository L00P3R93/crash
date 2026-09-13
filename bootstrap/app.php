<?php

use App\Domain\Aviator\Exceptions\BetAlreadySettledException;
use App\Domain\Aviator\Exceptions\DailyLimitExceededException;
use App\Domain\Aviator\Exceptions\HasPlayerMessage;
use App\Domain\Aviator\Exceptions\InvalidStakeException;
use App\Domain\Aviator\Exceptions\RoundClosedException;
use App\Domain\Aviator\Exceptions\SelfExcludedException;
use App\Domain\Aviator\Exceptions\WithdrawalsDisabledException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Loaded without the `web` middleware group — these are stateless
            // webhooks/USSD callbacks (no session/CSRF), guarded per-route instead.
            require __DIR__.'/../routes/webhooks.php';
            require __DIR__.'/../routes/ussd.php';
        },
    )
    // Private channel auth (routes/channels.php) is checked against the API
    // token guard, not the `web` session guard — web players never log in
    // via a Fortify session, only msisdn+PIN -> Sanctum token.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The domain layer signals game-rule violations with plain
        // RuntimeException subclasses (shared with USSD, which catches them
        // inline instead) — map them to 422s here for the API instead of
        // letting them fall through as 500s. `getMessage()` is written for
        // logs (it names internal ids like round/player/bet), never for
        // players — use each exception's `playerMessage()` instead, which
        // is the copy actually meant to reach the UI.
        $asUnprocessable = fn (HasPlayerMessage $e) => response()->json(['message' => $e->playerMessage()], 422);

        $exceptions->render(fn (RoundClosedException $e) => $asUnprocessable($e));
        $exceptions->render(fn (InvalidStakeException $e) => $asUnprocessable($e));
        $exceptions->render(fn (BetAlreadySettledException $e) => $asUnprocessable($e));
        $exceptions->render(fn (DailyLimitExceededException $e) => $asUnprocessable($e));
        $exceptions->render(fn (SelfExcludedException $e) => $asUnprocessable($e));
        // Only reachable via B2cService today (USSD-only withdrawals); wire
        // this up wherever WalletController::withdraw() stops being a 501 stub.
        $exceptions->render(fn (WithdrawalsDisabledException $e) => $asUnprocessable($e));
    })->create();
