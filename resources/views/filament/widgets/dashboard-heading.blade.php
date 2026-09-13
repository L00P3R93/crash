<div @class(['flex items-center gap-x-3', $description ? 'items-start' : 'items-center'])>
    @if ($icon)
        <x-filament::icon
            :icon="$icon"
            class="h-6 w-6 shrink-0 text-primary-500"
        />
    @endif

    <div>
        <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
            {{ $heading }}
        </h2>

        @if ($description)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $description }}
            </p>
        @endif
    </div>
</div>
