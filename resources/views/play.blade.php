<!DOCTYPE html>
<html lang="en" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Play Aviator — {{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">

        @fonts
        @vite(['resources/css/app.css', 'resources/css/aviator.css', 'resources/js/play.js'])
    </head>
    <body class="min-h-screen overflow-x-hidden bg-casino-black font-sans text-casino-white antialiased">
        <div class="relative flex min-h-screen flex-col px-4 py-4 lg:h-screen lg:overflow-hidden">

            {{-- Login --}}
            {{-- Visibility toggled via inline `style.display` from JS
                 (showAuthScreen/showGameScreen), not the `hidden` class —
                 game-screen below also carries `lg:flex` for its desktop
                 layout, and Tailwind's `hidden` and a responsive `display`
                 utility fight over the same property with the later one in
                 the compiled stylesheet winning regardless of class order,
                 so `hidden` alone would stop hiding anything at `lg:` and up. --}}
            <div id="auth-screen" style="display: none" class="mx-auto w-full max-w-md space-y-6">
                <div class="aviator-fade-up text-center">
                    <h1 class="font-display text-3xl font-semibold text-casino-white">Aviator</h1>
                    <p class="mt-2 text-sm text-casino-white/60">Sign in with the phone number and PIN you set over USSD.</p>
                </div>

                <form id="login-form" class="aviator-fade-up space-y-4 rounded-xl border border-white/10 bg-casino-card p-6" style="animation-delay: 80ms">
                    <div>
                        <label for="login-msisdn" class="block text-sm font-medium text-casino-white/80">Phone number</label>
                        <div class="relative mt-1">
                            <x-heroicon-o-device-phone-mobile class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-casino-white/40" />
                            <input
                                id="login-msisdn" name="msisdn" type="tel" required autocomplete="username"
                                placeholder="2547XXXXXXXX"
                                class="w-full rounded-lg border border-white/10 bg-casino-black py-2 pl-10 pr-3 text-casino-white placeholder-casino-white/30 focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="login-pin" class="block text-sm font-medium text-casino-white/80">PIN</label>
                        <div class="relative mt-1">
                            <x-heroicon-o-lock-closed class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-casino-white/40" />
                            <input
                                id="login-pin" name="pin" type="password" inputmode="numeric" maxlength="4" required
                                autocomplete="current-password"
                                class="w-full rounded-lg border border-white/10 bg-casino-black py-2 pl-10 pr-3 text-casino-white placeholder-casino-white/30 focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold"
                            >
                        </div>
                    </div>

                    <p id="login-error" class="hidden items-center gap-1.5 text-sm text-loss">
                        <x-heroicon-o-exclamation-triangle class="size-4 shrink-0" />
                        <span id="login-error-text"></span>
                    </p>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gold px-4 py-2 font-semibold text-casino-black transition hover:brightness-110">
                        <x-heroicon-o-arrow-right-end-on-rectangle class="size-5" />
                        Sign in
                    </button>

                    <p class="text-center text-xs text-casino-white/50">
                        Forgot PIN? <span class="text-casino-white/70">Reset via USSD</span>
                    </p>
                </form>

                <p class="aviator-fade-up flex items-center justify-center gap-1.5 text-center text-xs text-casino-white/40" style="animation-delay: 160ms">
                    <x-heroicon-o-information-circle class="size-4 shrink-0" />
                    Never played before? Dial the USSD code and choose "Play Aviator" to set your PIN first.
                </p>
            </div>

            {{-- Game --}}
            <div id="game-screen" style="display: none" class="mx-auto w-full max-w-md space-y-3 lg:flex lg:h-full lg:max-w-[1600px] lg:min-h-0 lg:flex-col lg:space-y-3">
                {{-- Account/wallet FAB — replaces the old always-on header
                     bar (phone, balance, mute, sign out), which ate a full
                     row above the graph on every screen size for
                     information a player only needs to check occasionally.
                     The balance stays glanceable on the FAB itself; tapping
                     it opens <x-account-panel /> for everything else. --}}
                <button id="account-fab" type="button" class="fixed bottom-4 right-4 z-30 inline-flex items-center gap-2 rounded-full border border-white/10 bg-casino-card px-4 py-3 shadow-lg shadow-black/40 transition hover:brightness-110">
                    <x-wallet-balance />
                </button>

                {{-- Below `lg:`, everything stacks in one column: graph,
                     then betting controls directly beneath it, then the
                     active-players sidebar. At `lg:` and up the graph +
                     betting controls form a left column (filling the full
                     viewport height, no page scroll) and the active-players
                     table becomes a right sidebar stretched to match — the
                     bet/cash-out card stays docked to the graph either way,
                     never pushed off-screen by other content. --}}
                <div class="flex flex-col gap-3 lg:min-h-0 lg:flex-1 lg:flex-row lg:items-stretch lg:gap-4">

                <div class="flex min-w-0 flex-1 flex-col gap-3 lg:min-h-0">

                <div class="aviator-fade-up relative lg:min-h-0 lg:flex-1" style="animation-delay: 60ms">
                    <div class="pointer-events-none absolute -inset-6 -z-10 rounded-full bg-gold-shine/20 blur-3xl"></div>

                    <x-flight-canvas class="aspect-[4/3] sm:aspect-[16/10] lg:aspect-auto lg:h-full">
                        <div class="flex w-full items-center justify-between text-xs font-medium text-casino-white/60">
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-hashtag class="size-3.5" />
                                Round <span id="round-number">—</span>
                            </span>

                            {{-- Visibility toggled via inline `style.display` from JS (setStatusBadge),
                                 not a `hidden` class — Tailwind's `hidden` and `inline-flex` fight over
                                 the same `display` property, and whichever is later in the compiled
                                 stylesheet wins regardless of HTML class order, so mixing them on one
                                 element is unreliable. --}}
                            <span id="status-idle" class="items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-casino-white/70">
                                <x-heroicon-o-signal class="size-3.5" />
                                Connecting
                            </span>
                            <span id="status-betting" style="display: none" class="aviator-badge--live items-center gap-1 rounded-full bg-gold-shine/15 px-2.5 py-1 text-gold-shine ring-1 ring-gold-shine/30">
                                <x-heroicon-o-clock class="size-3.5" />
                                Betting open
                            </span>
                            <span id="status-running" style="display: none" class="items-center gap-1 rounded-full bg-gold/15 px-2.5 py-1 text-gold ring-1 ring-gold/30">
                                <x-heroicon-o-bolt class="size-3.5" />
                                In flight
                            </span>
                            <span id="status-crashed" style="display: none" class="items-center gap-1 rounded-full bg-loss/15 px-2.5 py-1 text-loss ring-1 ring-loss/30">
                                <x-heroicon-o-exclamation-triangle class="size-3.5" />
                                Crashed
                            </span>
                            <span id="status-settled" style="display: none" class="items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-casino-white/70">
                                <x-heroicon-o-check-circle class="size-3.5" />
                                Settled
                            </span>
                        </div>

                        <x-slot:footer>
                            <p id="betting-countdown" class="hidden text-sm text-casino-white/60">Place your bet before the window closes.</p>
                            <p id="crash-reveal" class="hidden text-sm font-semibold text-loss"></p>
                        </x-slot:footer>
                    </x-flight-canvas>
                </div>

                <div id="result-banner" style="display: none" class="aviator-fade-up shrink-0 items-center gap-3 rounded-xl border p-4 text-sm font-medium data-[outcome=won]:border-win/50 data-[outcome=won]:bg-win/10 data-[outcome=won]:text-win data-[outcome=lost]:border-loss/50 data-[outcome=lost]:bg-loss/10 data-[outcome=lost]:text-loss">
                    <x-heroicon-s-trophy id="result-icon-won" class="hidden size-6 shrink-0" />
                    <x-heroicon-s-x-circle id="result-icon-lost" class="hidden size-6 shrink-0" />
                    <span id="result-text"></span>
                </div>

                {{-- Docked directly under the graph rather than off in a
                     sidebar — betting and cashing out are the actions a
                     player needs while watching the multiplier, so this
                     stays in the same view as the graph at every size. --}}
                <div id="bet-panel" class="hidden shrink-0 space-y-3 rounded-xl border border-white/10 bg-casino-card p-6 lg:space-y-2 lg:p-4">
                    <label for="stake-input" class="block text-sm font-medium text-casino-white/80">Stake (KSh)</label>
                    <div class="relative">
                        <x-heroicon-o-banknotes class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-casino-white/40" />
                        <input
                            id="stake-input" type="number" step="1" value="50"
                            class="w-full rounded-lg border border-white/10 bg-casino-black py-2 pl-10 pr-3 text-casino-white focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold lg:py-1.5"
                        >
                    </div>

                    <div class="flex gap-2" role="group" aria-label="Quick stake amounts">
                        @foreach ([50, 100, 500, 1000] as $amount)
                            <button type="button" data-stake-chip="{{ $amount }}" class="flex-1 rounded-lg border border-white/10 bg-white/5 py-2 text-xs font-semibold text-casino-white/80 transition hover:border-gold/50 hover:text-gold lg:py-1.5">
                                {{ $amount }}
                            </button>
                        @endforeach
                    </div>

                    <p class="flex items-center gap-1.5 text-xs text-casino-white/50">
                        <x-heroicon-o-information-circle class="size-3.5 shrink-0" />
                        <span id="stake-hint"></span>
                    </p>
                    <p id="bet-error" class="hidden items-center gap-1.5 text-sm text-loss">
                        <x-heroicon-o-exclamation-triangle class="size-4 shrink-0" />
                        <span id="bet-error-text"></span>
                    </p>
                    <button id="place-bet-btn" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gold px-4 py-2 font-semibold text-casino-black transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50 lg:py-1.5">
                        <x-heroicon-o-bolt class="size-5" />
                        <span id="place-bet-btn-label">Place bet</span>
                    </button>
                </div>

                <div id="active-bet-panel" class="hidden shrink-0 space-y-3 rounded-xl border border-white/10 bg-casino-card p-6 lg:space-y-2 lg:p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-casino-white/60">
                            <x-heroicon-o-ticket class="size-4" />
                            Bet in play
                        </span>
                        <span id="active-bet-stake" class="font-semibold text-casino-white"></span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-black/20 px-3 py-2 text-sm">
                        <span class="flex items-center gap-1.5 text-casino-white/60">
                            <x-heroicon-o-arrow-trending-up class="size-4 text-gold" />
                            Potential payout
                        </span>
                        <span id="potential-payout" class="font-semibold text-gold">KSh 0.00</span>
                    </div>
                    <p id="cashout-error" class="hidden items-center gap-1.5 text-sm text-loss">
                        <x-heroicon-o-exclamation-triangle class="size-4 shrink-0" />
                        <span id="cashout-error-text"></span>
                    </p>
                    <button id="cashout-btn" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-win px-4 py-2 font-semibold text-casino-black transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50 lg:py-1.5">
                        <x-heroicon-o-banknotes class="size-5" />
                        <span id="cashout-btn-label">Cash out</span>
                    </button>
                </div>

                </div>

                {{-- Sidebar: the active-players table alone now (the old
                     "Live activity" feed duplicated the same information
                     once this table shipped, so it was dropped). Paginated
                     10-per-page rather than a tall scrolling list, so it's
                     naturally sized instead of stretched to match the
                     graph column. --}}
                <div class="min-h-0 lg:w-[380px] lg:shrink-0">
                    <x-active-players-table />
                </div>

                </div>
            </div>
        </div>

        <x-account-panel />
    </body>
</html>
