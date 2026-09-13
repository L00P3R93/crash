<x-filament-panels::page>
    <form wire:submit="lookup" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                Look up round
            </x-filament::button>
        </div>
    </form>

    @if ($round)
        <x-filament::section
            class="mt-6"
            icon="heroicon-o-rocket-launch"
            :heading="'Round '.$round->round_number"
            :description="ucfirst($round->status->value)"
        >
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Crash multiplier</dt>
                    <dd class="mt-1 font-mono text-lg font-semibold text-gray-950 dark:text-white">{{ $round->crash_multiplier }}x</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nonce</dt>
                    <dd class="mt-1 font-mono">{{ $round->nonce }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Server seed hash (published before betting)</dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 p-2 font-mono text-sm dark:bg-white/5">{{ $round->server_seed_hash }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Server seed (revealed after settlement)</dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 p-2 font-mono text-sm dark:bg-white/5">{{ $round->server_seed ?? '— not yet revealed —' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client seed</dt>
                    <dd class="mt-1 font-mono break-all">{{ $round->client_seed }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex items-center gap-x-4">
                <x-filament::button wire:click="recompute" color="gray" icon="heroicon-o-arrow-path">
                    Recompute &amp; verify
                </x-filament::button>

                @if (! is_null($verified))
                    <x-filament::badge :color="$verified ? 'success' : 'danger'" :icon="$verified ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'">
                        {{ $verified ? 'Verified — reproduces exactly' : 'Mismatch — does not verify' }}
                    </x-filament::badge>
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
