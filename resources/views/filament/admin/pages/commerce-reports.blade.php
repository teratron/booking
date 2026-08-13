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
                <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.financial_records.form.object') }} — {{ __('panel.navigation.geography') }}</span>
                <select wire:model.live="countryId" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">—</option>
                    @foreach ($this->countries() as $id => $code)
                        <option value="{{ $id }}">{{ $code }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @php $summary = $this->summary(); @endphp
        @foreach ([
            'active_placements', 'ending_30', 'ending_14', 'ending_7', 'ending_3',
            'expired_placements', 'no_term_set', 'free_placements', 'paid_bump_count', 'active_campaigns',
        ] as $key)
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __("panel.commerce_reports.{$key}") }}</div>
                <div class="text-2xl font-semibold">{{ $summary[$key] }}</div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
