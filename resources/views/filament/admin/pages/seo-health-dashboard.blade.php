<x-filament-panels::page>
    @php $warnings = $this->warnings(); @endphp

    @if ($this->mapTileKeyMissing())
        <div class="fi-section rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/20">
            <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
                {{ __('panel.seo_health.map_tile_key_missing') }}
            </p>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
        @foreach ($warnings as $key => $rows)
            <a href="#warning-{{ $key }}" class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __("panel.seo_health.warnings.{$key}") }}</div>
                <div class="text-2xl font-semibold {{ count($rows) > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ count($rows) }}</div>
            </a>
        @endforeach
    </div>

    @foreach ($warnings as $key => $rows)
        <div id="warning-{{ $key }}" class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold">{{ __("panel.seo_health.warnings.{$key}") }}</h3>

            @if (count($rows) === 0)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.seo_health.empty_state') }}</p>
            @else
                <table class="fi-ta-table mt-2 w-full text-start">
                    <thead>
                        <tr>
                            <th class="p-2 text-start">{{ __('panel.seo_health.columns.entity_type') }}</th>
                            <th class="p-2 text-start">{{ __('panel.seo_health.columns.locale') }}</th>
                            <th class="p-2 text-start">{{ __('panel.seo_health.columns.name') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="p-2">{{ $this->entityTypeLabel($row['entityType']) }}</td>
                                <td class="p-2">{{ strtoupper($row['locale']) }}</td>
                                <td class="p-2">{{ $row['name'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</x-filament-panels::page>
