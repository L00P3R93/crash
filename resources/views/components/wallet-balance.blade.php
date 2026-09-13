{{--
    Reusable wallet-balance display — the nav header, the play screen, and
    the wallet top-up/cash-out modal all show the same figure. play.js
    updates every `[data-wallet-balance-amount]` on the page whenever the
    player's balance changes, so any number of instances stay in sync
    without each needing its own fetch.
--}}
<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
    <x-heroicon-o-banknotes class="size-4 shrink-0 text-gold" />
    <span data-wallet-balance-amount class="font-sans font-semibold text-casino-white">KSh 0.00</span>
</div>
