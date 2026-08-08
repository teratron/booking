<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="fi-ta-table w-full text-start">
            <thead>
                <tr>
                    <th class="p-2 text-start">{{ __('panel.translation_report.columns.entity') }}</th>
                    @foreach ($this->selectableLocales() as $locale)
                        <th class="p-2 text-start">{{ strtoupper($locale) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $rows = collect($this->summary())->groupBy(fn (array $row) => $row['entity']->modelClass); @endphp
                @foreach ($rows as $modelClass => $byLocale)
                    <tr>
                        <td class="p-2 font-medium">{{ $this->entityLabel($modelClass) }}</td>
                        @foreach ($byLocale as $row)
                            <td class="p-2">
                                {{ $row['translated'] }}/{{ $row['total'] }}
                                @if ($row['needsReview'] > 0)
                                    <span class="text-amber-600">({{ __('panel.translation_report.columns.needs_review_count', ['count' => $row['needsReview']]) }})</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex gap-4">
        <label class="flex-1">
            <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.translation_report.columns.entity') }}</span>
            <select wire:model.live="entityClass" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                @foreach ($this->entities() as $entity)
                    <option value="{{ $entity->modelClass }}">{{ $this->entityLabel($entity->modelClass) }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex-1">
            <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.languages.title') }}</span>
            <select wire:model.live="locale" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                @foreach ($this->selectableLocales() as $locale)
                    <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                @endforeach
            </select>
        </label>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
