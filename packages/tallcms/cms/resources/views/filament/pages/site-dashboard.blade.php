<x-filament::section>
    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('tallcms::pages.site_dashboard.domain') }}:
            <span class="font-medium text-gray-950 dark:text-white">{{ $site?->domain }}</span>
        </div>

        @if ($frontendUrl)
            <x-filament::link :href="$frontendUrl" target="_blank" icon="heroicon-m-arrow-top-right-on-square">
                {{ __('tallcms::pages.site_dashboard.open_website') }}
            </x-filament::link>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook($renderHook, data: ['site' => $site]) }}
    </div>
</x-filament::section>
