<x-filament-panels::page>
    @php
        $summary = $this->summary();
    @endphp

    @if ($summary)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.statistics.object_name') }}</div>
            <div class="text-xl font-semibold">{{ $summary->objectName }}</div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                'page_views' => $summary->pageViews,
                'photo_views' => $summary->photoViews,
                'contact_clicks_total' => $summary->contactClicksTotal,
                'favorite_count' => $summary->favoriteCount,
            ] as $key => $value)
                <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __("panel.cabinet.statistics.{$key}") }}</div>
                    <div class="text-2xl font-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.statistics.channel_breakdown_title') }}</div>

            @if (count($summary->channelClicks) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.statistics.channel_breakdown_empty') }}</p>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($summary->channelClicks as $channel)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800">
                            <span class="text-sm font-medium">{{ $channel->label }}</span>
                            <span class="text-lg font-semibold">{{ $channel->count }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.statistics.traffic_source_title') }}</div>

            @if (count($summary->trafficSources) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.statistics.traffic_source_empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody>
                            @foreach ($summary->trafficSources as $source)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                    <td class="py-2 pr-4 font-medium">{{ __('panel.cabinet.statistics.traffic_channels.'.$source->channel->value) }}</td>
                                    <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $source->domain ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                                        @if ($source->campaign)
                                            {{ __('panel.cabinet.statistics.traffic_source_campaign', ['campaign' => $source->campaign]) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2 text-right font-semibold">{{ $source->count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
