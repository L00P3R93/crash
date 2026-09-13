{{--
    Everything that used to live in a permanent header bar above the graph
    (masked phone number, mute toggle, sign out) plus the top-up/cash-out
    modal, merged into one panel behind the balance FAB (see play.blade.php)
    instead of three separate always-visible controls. Slides up from the
    bottom on small screens, in from the right as a full-height drawer at
    `lg:` and up — resources/css/aviator.css's `.account-panel` rules drive
    the slide direction per breakpoint; play.js toggles the `--open` class.

    Top-up/withdraw are wired to POST /api/wallet/topup and /api/wallet/withdraw
    — both currently stubbed (501, see App\Http\Controllers\Api\WalletController)
    until the web-triggered M-Pesa STK/withdrawal integration is built as a
    follow-up. The UI is real; the backend behind it isn't yet.
--}}
<div id="account-panel-overlay" style="display: none" class="fixed inset-0 z-50 flex items-end justify-center lg:items-stretch lg:justify-end">
    <div id="account-panel-backdrop" class="absolute inset-0 bg-black/60"></div>

    <div id="account-panel" class="account-panel relative flex max-h-[85vh] w-full max-w-sm flex-col overflow-hidden rounded-t-2xl border border-white/10 bg-casino-card lg:h-full lg:max-h-none lg:rounded-none lg:rounded-l-2xl">
        {{-- Fixed header --}}
        <div class="flex items-center justify-between border-b border-white/10 p-4">
            <p class="flex items-center gap-1.5 text-sm text-casino-white/60">
                <x-heroicon-o-device-phone-mobile class="size-4" />
                <span id="account-panel-msisdn">—</span>
            </p>
            <button id="account-panel-close-btn" type="button" class="rounded-lg p-1.5 text-casino-white/50 transition hover:bg-white/5 hover:text-casino-white">
                <x-heroicon-o-x-mark class="size-5" />
            </button>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 overflow-y-auto p-4">
            <div class="mb-4 flex items-center justify-between rounded-lg bg-black/20 p-3">
                <x-wallet-balance class="text-lg" />
                <div class="flex items-center gap-1">
                    <button id="account-panel-sound-toggle-btn" type="button" aria-pressed="false" class="rounded-lg p-2 text-casino-white/50 transition hover:bg-white/10 hover:text-casino-white">
                        <x-heroicon-o-speaker-wave id="account-panel-sound-on-icon" class="hidden size-4" />
                        <x-heroicon-o-speaker-x-mark id="account-panel-sound-off-icon" class="size-4" />
                    </button>
                    <button id="account-panel-signout-btn" type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm text-casino-white/50 transition hover:bg-white/10 hover:text-casino-white">
                        <x-heroicon-o-arrow-right-start-on-rectangle class="size-4" />
                        Sign out
                    </button>
                </div>
            </div>

            <div class="flex gap-1 rounded-lg bg-black/20 p-1" role="tablist">
                <button type="button" data-wallet-tab="topup" class="flex-1 rounded-md px-3 py-1.5 text-sm font-semibold transition" role="tab">Top up</button>
                <button type="button" data-wallet-tab="withdraw" class="flex-1 rounded-md px-3 py-1.5 text-sm font-semibold transition" role="tab">Cash out</button>
            </div>

            <div data-wallet-panel="topup" class="mt-3 space-y-3">
                <label for="topup-amount" class="block text-sm font-medium text-casino-white/80">Amount (KSh)</label>
                <div class="relative">
                    <x-heroicon-o-banknotes class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-casino-white/40" />
                    <input id="topup-amount" type="number" step="1" value="100" class="w-full rounded-lg border border-white/10 bg-casino-black py-2 pl-10 pr-3 text-casino-white focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold">
                </div>
                <div class="flex gap-2">
                    @foreach ([100, 250, 500, 1000] as $amount)
                        <button type="button" data-amount-chip="topup" data-amount="{{ $amount }}" class="flex-1 rounded-lg border border-white/10 bg-white/5 py-2 text-xs font-semibold text-casino-white/80 transition hover:border-gold/50 hover:text-gold">
                            {{ $amount }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div data-wallet-panel="withdraw" class="mt-3 hidden space-y-3">
                <label for="withdraw-amount" class="block text-sm font-medium text-casino-white/80">Amount (KSh)</label>
                <div class="relative">
                    <x-heroicon-o-banknotes class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-casino-white/40" />
                    <input id="withdraw-amount" type="number" step="1" value="100" class="w-full rounded-lg border border-white/10 bg-casino-black py-2 pl-10 pr-3 text-casino-white focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold">
                </div>
                <div class="flex gap-2">
                    @foreach ([100, 250, 500, 1000] as $amount)
                        <button type="button" data-amount-chip="withdraw" data-amount="{{ $amount }}" class="flex-1 rounded-lg border border-white/10 bg-white/5 py-2 text-xs font-semibold text-casino-white/80 transition hover:border-gold/50 hover:text-gold">
                            {{ $amount }}
                        </button>
                    @endforeach
                </div>
            </div>

            <p id="wallet-modal-message" class="mt-4 hidden items-start gap-1.5 rounded-lg bg-black/20 p-3 text-sm text-casino-white/70">
                <x-heroicon-o-information-circle class="size-4 shrink-0 translate-y-0.5" />
                <span id="wallet-modal-message-text"></span>
            </p>
        </div>

        {{-- Sticky footer --}}
        <div class="border-t border-white/10 p-4">
            <button id="wallet-modal-submit-btn" type="button" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gold px-4 py-2 font-semibold text-casino-black transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50">
                <x-heroicon-o-banknotes class="size-5" />
                <span id="wallet-modal-submit-label">Pay with M-Pesa</span>
            </button>
        </div>
    </div>
</div>
