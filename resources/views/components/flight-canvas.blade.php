@props(['state' => 'waiting'])

{{--
    Gold-casino flight gauge — a gold-bezel frame around a near-black dial
    face, a rising contrail line, and a large centered tabular-nums
    multiplier. Used only on the authenticated play screen
    (resources/js/play.js drives it from round/bet state) — the gauge never
    appears on the welcome page.

    No plane/rocket icon rides the line on purpose — the theme's own
    principle is "numbers do the storytelling, not icons."
--}}
<div
    {{ $attributes->merge(['class' => 'aviator-gauge-bezel relative overflow-hidden rounded-2xl bg-casino-dark']) }}
    data-flight-state="{{ $state }}"
>
    <svg viewBox="0 0 300 150" preserveAspectRatio="none" class="absolute inset-0 h-full w-full">
        <defs>
            <linearGradient id="flight-path-gradient" x1="0" y1="1" x2="0" y2="0">
                <stop offset="0%" stop-color="#f5c542" stop-opacity="0" />
                <stop offset="100%" stop-color="#f5c542" stop-opacity="0.9" />
            </linearGradient>
            <linearGradient id="flight-fill-gradient" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f5c542" stop-opacity="0.22" />
                <stop offset="100%" stop-color="#f5c542" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path id="flight-fill" fill="url(#flight-fill-gradient)" stroke="none" />
        <path id="flight-path" class="aviator-path" fill="none" stroke="url(#flight-path-gradient)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>

    {{-- The one animated flourish the theme allows outside the live line
         itself — a needle-sweep across the bezel, like a gauge calibrating,
         played once when a round is about to start. Triggered by toggling
         the `.aviator-sweep` class on/off. --}}
    <div id="flight-sweep" class="pointer-events-none absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-gold-shine/40 to-transparent opacity-0"></div>

    {{-- Pinned to the bezel's own top edge rather than living inside the
         centered column below — it's a HUD readout (round/status), not
         part of the multiplier's centered composition, so it shouldn't
         drift up or down with that column's vertical centering. --}}
    <div class="absolute inset-x-0 top-0 z-10 p-4">
        {{ $slot }}
    </div>

    <div class="relative z-10 flex h-full flex-col items-center justify-center gap-4 p-6 text-center">
        <div id="multiplier-display" class="aviator-multiplier--idle font-sans text-6xl font-black tabular-nums text-casino-white sm:text-7xl">1.00x</div>

        {{ $footer ?? '' }}
    </div>
</div>
