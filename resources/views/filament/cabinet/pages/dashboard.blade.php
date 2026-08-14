<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $actions = $this->quickActions();
    @endphp

    @if ($summary)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.dashboard.object_name') }}</div>
                    <div class="text-xl font-semibold">{{ $summary->objectName }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.objects.columns.availability') }}</div>
                    <div class="text-lg font-medium">{{ __('panel.objects.availability.'.$summary->availabilityStatus) }}</div>
                </div>
            </div>
        </div>

        @if ($summary->expiryWarningActive)
            <div class="fi-section rounded-xl bg-amber-50 p-4 ring-1 ring-amber-300 dark:bg-amber-500/10 dark:ring-amber-500/30">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                    @if ($summary->expiryWarningDaysRemaining === 0)
                        {{ __('panel.cabinet.dashboard.expiry_warning_today') }}
                    @else
                        {{ __('panel.cabinet.dashboard.expiry_warning_days', ['days' => $summary->expiryWarningDaysRemaining]) }}
                    @endif
                </p>
            </div>
        @endif

        @if ($summary->isStale)
            <div class="fi-section rounded-xl bg-blue-50 p-4 ring-1 ring-blue-300 dark:bg-blue-500/10 dark:ring-blue-500/30">
                <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    {{ __('panel.cabinet.dashboard.staleness_notice') }}
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.dashboard.active_package') }}</div>
                <div class="text-lg font-semibold">{{ $summary->packageName ?? __('panel.cabinet.dashboard.no_active_package') }}</div>
            </div>
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.dashboard.current_tier') }}</div>
                <div class="text-lg font-semibold">{{ $summary->tierLabel ?? '—' }}</div>
            </div>
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.dashboard.expires_at') }}</div>
                <div class="text-lg font-semibold">{{ $summary->placementExpiresAt?->format('d.m.Y') ?? '—' }}</div>
            </div>
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.dashboard.catalog_position') }}</div>
                <div class="text-lg font-semibold">{{ $summary->catalogPosition ?? __('panel.cabinet.dashboard.catalog_position_unavailable') }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                'views_today' => $summary->viewsToday,
                'views_this_week' => $summary->viewsThisWeek,
                'views_this_month' => $summary->viewsThisMonth,
                'views_all_time' => $summary->viewsAllTime,
                'messenger_clicks' => $summary->messengerClicks,
                'website_clicks' => $summary->websiteClicks,
            ] as $key => $value)
                <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __("panel.cabinet.dashboard.{$key}") }}</div>
                    <div class="text-2xl font-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-4 text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('panel.cabinet.dashboard.quick_actions_section') }}</div>
            <div class="flex flex-wrap gap-3">
                @foreach ($actions as $action)
                    @if ($action['url'])
                        <a
                            href="{{ $action['url'] }}"
                            class="fi-btn inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                        >
                            <x-filament::icon :icon="$action['icon']" class="h-4 w-4" />
                            {{ __("panel.cabinet.dashboard.quick_actions.{$action['key']}") }}
                        </a>
                    @else
                        <span
                            class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-400 dark:bg-gray-800 dark:text-gray-500"
                            title="{{ __('panel.cabinet.dashboard.quick_actions.not_yet_available') }}"
                        >
                            <x-filament::icon :icon="$action['icon']" class="h-4 w-4" />
                            {{ __("panel.cabinet.dashboard.quick_actions.{$action['key']}") }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
