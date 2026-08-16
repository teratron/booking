<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap gap-4">
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.financial_records.filters.from') }}</span>
                <input type="date" wire:model.live="periodFrom" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
            </label>
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.financial_records.filters.until') }}</span>
                <input type="date" wire:model.live="periodUntil" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
            </label>
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.navigation.geography') }}</span>
                <select wire:model.live="countryId" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">—</option>
                    @foreach ($this->countries() as $id => $code)
                        <option value="{{ $id }}">{{ $code }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.analytics_report.filters.territory') }}</span>
                <select wire:model.live="territoryId" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">—</option>
                    @foreach ($this->territories() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.analytics_report.filters.category') }}</span>
                <select wire:model.live="objectTypeId" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">—</option>
                    @foreach ($this->objectTypes() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.analytics_report.filters.language') }}</span>
                <select wire:model.live="locale" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">—</option>
                    @foreach ($this->languages() as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.analytics_report.filters.banner') }}</span>
                <select wire:model.live="bannerId" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">—</option>
                    @foreach ($this->banners() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex-1 min-w-[10rem]">
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.analytics_report.filters.object') }}</span>
                <input type="number" wire:model.live="objectId" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
            </label>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @php $summary = $this->summary(); @endphp
        @foreach ($summary as $kind => $count)
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __("panel.analytics_report.kinds.{$kind}") }}</div>
                <div class="text-2xl font-semibold">{{ $count }}</div>
            </div>
        @endforeach
    </div>

    @php $derived = $this->derivedFigures(); @endphp
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.new_owner_count') }}</div>
            <div class="text-2xl font-semibold">{{ $derived['new_owner_count'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.new_object_count') }}</div>
            <div class="text-2xl font-semibold">{{ $derived['new_object_count'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.bump_count') }}</div>
            <div class="text-2xl font-semibold">{{ $derived['bump_count'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.published_promotion_count') }}</div>
            <div class="text-2xl font-semibold">{{ $derived['published_promotion_count'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.pending_moderation_count') }}</div>
            <div class="text-2xl font-semibold">{{ $derived['pending_moderation_count'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.banner_click_through_rate') }}</div>
            <div class="text-2xl font-semibold">{{ number_format($derived['banner_click_through_rate'] * 100, 2) }}%</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold">{{ __('panel.analytics_report.derived.most_viewed_objects') }}</h3>
            <ol class="mt-2 list-decimal space-y-1 ps-5 text-sm">
                @forelse ($derived['most_viewed_objects'] as $row)
                    <li>{{ $row['name'] }} — {{ $row['views'] }}</li>
                @empty
                    <li class="list-none text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.empty_state') }}</li>
                @endforelse
            </ol>
        </div>
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold">{{ __('panel.analytics_report.derived.most_popular_categories') }}</h3>
            <ol class="mt-2 list-decimal space-y-1 ps-5 text-sm">
                @forelse ($derived['most_popular_categories'] as $row)
                    <li>{{ $row['name'] }} — {{ $row['views'] }}</li>
                @empty
                    <li class="list-none text-gray-500 dark:text-gray-400">{{ __('panel.analytics_report.derived.empty_state') }}</li>
                @endforelse
            </ol>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
