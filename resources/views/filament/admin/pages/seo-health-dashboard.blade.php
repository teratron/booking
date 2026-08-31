<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $warnings = $this->warnings();
    @endphp

    @if ($this->mapTileKeyMissing())
        <div class="fi-section rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/20">
            <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
                {{ __('panel.seo_health.map_tile_key_missing') }}
            </p>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
        @foreach ($summary as $key => $count)
            <a href="#warning-{{ $key }}" class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __("panel.seo_health.warnings.{$key}") }}</div>
                <div class="text-2xl font-semibold {{ $count > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($count) }}</div>
            </a>
        @endforeach
    </div>

    @foreach ($summary as $key => $count)
        @php $rows = $warnings[$key] ?? []; @endphp
        <div id="warning-{{ $key }}" class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold">{{ __("panel.seo_health.warnings.{$key}") }}</h3>

            @if ($count === 0)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.seo_health.empty_state') }}</p>
            @else
                @if (count($rows) < $count)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('panel.seo_health.sample_note', ['shown' => count($rows), 'total' => number_format($count)]) }}</p>
                @endif
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
