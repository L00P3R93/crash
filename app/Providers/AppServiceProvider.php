<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->overlayGameSettings();
        $this->configureDevProcesses();
    }

    /**
     * Wires the round scheduler into `composer run dev` (and `php artisan
     * dev`) alongside Reverb/queue/Vite, so one command starts everything
     * needed to actually see rounds run locally.
     *
     * Runs in boot() — which happens after every provider's register()
     * phase, including Horizon's own registration of its dev command below
     * — so this is guaranteed to be the last word on whether Horizon stays
     * in the list, regardless of provider order.
     */
    protected function configureDevProcesses(): void
    {
        if (! $this->app->runningInConsole() || ! class_exists(DevCommands::class)) {
            return;
        }

        DevCommands::artisan('aviator:run-game-loop', 'game-loop');

        // The framework's default 'queue' dev entry runs `queue:listen`,
        // which re-boots the whole framework for every single job — fine
        // for the odd job, but this game can queue dozens of broadcast
        // events per round (one per bot bet placed/cashed-out) and that
        // per-job boot cost snowballs into a multi-round backlog. Same
        // name ('queue'), higher priority as userland code, so this simply
        // replaces the default entry rather than running both.
        DevCommands::artisan('queue:work --tries=1', 'queue');

        // Horizon requires pcntl, which Windows PHP builds don't ship —
        // left in the list there, `composer run dev` crashes immediately
        // with "Call to undefined function pcntl_async_signals()". Horizon
        // itself excludes the plain queue worker when it registers (they'd
        // otherwise both try to work the same queue), so falling back here
        // means re-including that plain worker instead — now the faster
        // queue:work variant above.
        if (! extension_loaded('pcntl')) {
            DevCommands::except('horizon');
        }
    }

    /**
     * Lets the Filament "Game Settings" page (Phase 5) edit house edge, stake
     * bounds, etc. without a deploy — every domain service still just calls
     * config('aviator.*') unchanged, this overlay runs before any of them do.
     * A null column means "no override, use the .env-driven default".
     */
    protected function overlayGameSettings(): void
    {
        try {
            if (! Schema::hasTable('game_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $settings = DB::table('game_settings')->first();

        if (! $settings) {
            return;
        }

        foreach (['house_edge', 'min_stake', 'max_stake', 'max_multiplier', 'betting_window_seconds', 'post_round_pause_seconds', 'acceleration_k', 'winnings_tax_rate'] as $key) {
            // Property access, not array-style: `$settings` is the raw
            // stdClass a query builder ->first() returns, whose columns are
            // exactly whatever migrations have applied so far. A key added
            // to this list boots at least once — during its own `migrate`
            // run — before the column that backs it exists; `?? null`
            // (rather than a direct property read) keeps that a no-op
            // instead of a fatal "undefined property" error.
            if (($settings->{$key} ?? null) !== null) {
                config(["aviator.{$key}" => $settings->{$key}]);
            }
        }
    }

    /**
     * Named throttle groups shared across the USSD, M-Pesa webhook, and
     * player-action routes introduced in later phases, defined once here
     * rather than reinvented per route file.
     */
    protected function configureRateLimiting(): void
    {
        // Safaricom retries webhooks aggressively on slow responses; this is
        // generous on purpose, it's a safety net against abuse, not normal traffic.
        RateLimiter::for('mpesa-webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('ussd', fn (Request $request) => Limit::perMinute(60)->by($request->input('sessionId', $request->ip())));

        RateLimiter::for('aviator-actions', fn (Request $request) => Limit::perMinute(30)->by($request->user() ? $request->user()->id : $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
