<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }} — Provably-fair Aviator</title>
        <meta name="description" content="A provably-fair Aviator-style crash game. Watch the multiplier climb, cash out before it crashes.">

        <link rel="icon" href="/favicon.ico" sizes="any">

        @fonts
        @vite(['resources/css/app.css', 'resources/css/aviator.css'])
    </head>
    <body class="min-h-screen overflow-x-hidden bg-casino-black font-sans text-casino-white antialiased">

        {{-- Hero: single centered column, no split, no gauge — the gauge is
             reserved for the play screen. Premium here comes from spacing
             and type, not added visual elements. --}}
        <section class="relative flex min-h-[75vh] flex-col items-center justify-center overflow-hidden px-4 py-20 text-center sm:px-6">
            <div class="pointer-events-none absolute left-1/2 top-1/2 -z-10 h-[28rem] w-[28rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold-shine/10 blur-3xl"></div>

            <div class="aviator-fade-up">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-gold">{{ config('app.name') }}</p>
                <div class="aviator-hairline mx-auto mt-4 w-16"></div>

                <h1 class="mx-auto mt-6 max-w-xl font-display text-4xl font-semibold leading-tight text-casino-white sm:text-5xl">
                    Watch it climb. Call your moment.
                </h1>
                <p class="mx-auto mt-4 max-w-md text-lg text-casino-white/70">
                    Stake what you choose, watch the multiplier rise, and cash out the instant it feels right — provably fair, every round.
                </p>
            </div>

            <div class="aviator-fade-up mt-10 flex w-full max-w-sm flex-col items-stretch gap-3 sm:max-w-none sm:flex-row sm:justify-center" style="animation-delay: 80ms">
                @if ($serviceCode = config('africastalking.service_code'))
                    <a
                        href="tel:{{ rawurlencode($serviceCode) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-gold px-6 py-3 font-semibold text-casino-black shadow-lg shadow-black/20 transition hover:bg-gold-2"
                    >
                        <x-heroicon-o-device-phone-mobile class="size-5" />
                        Dial {{ $serviceCode }} to Join
                    </a>
                @endif
                <a
                    href="{{ route('play') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-gold/50 px-6 py-3 font-semibold text-casino-white transition hover:border-gold hover:bg-white/5"
                >
                    Log In
                </a>
            </div>
        </section>

        {{-- How it works --}}
        <section class="bg-casino-card px-4 py-16 sm:px-6">
            <div class="aviator-fade-up mx-auto max-w-4xl">
                <h2 class="text-center font-display text-2xl font-semibold text-casino-white">How it works</h2>
                <ol class="mt-10 grid grid-cols-1 gap-8 sm:grid-cols-4">
                    @foreach ([
                        'Dial the USSD code',
                        'Create your PIN',
                        'Load your wallet via M-Pesa',
                        'Bet and cash out live',
                    ] as $index => $step)
                        <li class="flex flex-col items-center gap-3 text-center">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full border border-gold font-display text-sm font-semibold text-gold">
                                {{ $index + 1 }}
                            </span>
                            <span class="text-sm text-casino-white/80">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>


        {{-- Trust bar
        <section class="bg-casino-black px-4 py-10 sm:px-6">
            <div class="aviator-fade-up mx-auto flex max-w-3xl flex-col divide-y divide-gold/25 sm:flex-row sm:divide-x sm:divide-y-0">
                <div class="flex flex-1 items-center justify-center gap-2 px-4 py-3 text-sm text-casino-white/70">
                    <x-heroicon-o-shield-check class="size-4 shrink-0 text-gold" />
                    Licensed &amp; registered — licence no. pending
                </div>
                <div class="flex flex-1 items-center justify-center gap-2 px-4 py-3 text-sm text-casino-white/70">
                    <x-heroicon-o-check-badge class="size-4 shrink-0 text-gold" />
                    Every round is verifiable
                </div>
                <div class="flex flex-1 items-center justify-center gap-2 px-4 py-3 text-sm text-casino-white/70">
                    <x-heroicon-o-bolt class="size-4 shrink-0 text-gold" />
                    Instant M-Pesa cash-out
                </div>
            </div>
        </section>
        --}}

        {{-- Footer --}}
        <footer class="border-t border-gold/20 bg-casino-black px-4 py-8 text-center text-xs text-casino-white/50">
            <p class="mx-auto max-w-xl">
                For entertainment purposes only. Play responsibly — set limits and never chase losses.
                Players must be 18 years or older.
            </p>
            <p class="mt-2">Licence no. pending · Support: <a href="mailto:support@example.com" class="underline decoration-casino-white/30 hover:text-casino-white">support@example.com</a></p>
            <p class="mt-4">
                <a href="{{ route('filament.admin.auth.login') }}" class="inline-flex items-center gap-1 underline decoration-casino-white/20 underline-offset-2 hover:text-casino-white hover:decoration-casino-white/50">
                    <x-heroicon-o-lock-closed class="size-3.5" />
                    Staff sign in
                </a>
            </p>
        </footer>
    </body>
</html>
