{{-- Live "active players this round" table — every bet placed on the
    current round, masked phone + stake + result, paginated 10 at a time
    (see play.js's activePlayers pagination) rather than a tall scrolling
    list. New rows pop in individually as bets arrive on whichever page is
    currently showing; other pages update silently until you page to them. --}}
<div class="aviator-fade-up flex flex-col rounded-xl border border-white/10 bg-casino-card/60 p-4" style="animation-delay: 160ms">
    <p class="mb-2 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-casino-white/50">
        <x-heroicon-o-table-cells class="size-3.5" />
        Active players this round
    </p>
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="text-xs uppercase tracking-wide text-casino-white/40">
                <th class="pb-1.5 font-medium">Player</th>
                <th class="pb-1.5 font-medium">Stake</th>
                <th class="pb-1.5 font-medium text-right">Result</th>
            </tr>
        </thead>
        <tbody id="active-players-body">
            <tr id="active-players-empty">
                <td colspan="3" class="py-2 text-casino-white/40">No bets yet this round.</td>
            </tr>
        </tbody>
    </table>

    <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-3 text-xs text-casino-white/60">
        <button id="active-players-prev-btn" type="button" disabled class="inline-flex items-center gap-1 rounded-lg px-2 py-1 font-medium transition hover:bg-white/5 hover:text-casino-white disabled:cursor-not-allowed disabled:opacity-30">
            <x-heroicon-o-chevron-left class="size-3.5" />
            Previous
        </button>
        <span id="active-players-page-label">Page 1 of 1</span>
        <button id="active-players-next-btn" type="button" disabled class="inline-flex items-center gap-1 rounded-lg px-2 py-1 font-medium transition hover:bg-white/5 hover:text-casino-white disabled:cursor-not-allowed disabled:opacity-30">
            Next
            <x-heroicon-o-chevron-right class="size-3.5" />
        </button>
    </div>
</div>
