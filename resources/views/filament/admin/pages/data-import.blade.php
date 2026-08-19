<x-filament-panels::page>
    @if ($step === 'upload')
        <form wire:submit="parseFile" class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4">
                <label class="flex-1">
                    <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.data_transfer.import.fields.kind') }}</span>
                    <select wire:model="kind" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="">—</option>
                        @foreach ($this->kindOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('kind') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
                </label>

                <label class="flex-1">
                    <span class="fi-fo-field-wrp-label text-sm font-medium">{{ __('panel.data_transfer.import.fields.file') }}</span>
                    <input type="file" wire:model="file" accept=".xlsx,.csv" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                    @error('file') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit">
                    {{ __('panel.data_transfer.import.actions.parse') }}
                </x-filament::button>
            </div>
        </form>
    @endif

    @if ($step === 'mapping')
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ trans_choice('panel.data_transfer.import.mapping_intro', $totalRows ?? 0, ['count' => $totalRows]) }}
            </p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($this->targetColumns() as $column)
                    <label class="flex-1">
                        <span class="fi-fo-field-wrp-label text-sm font-medium">{{ $column->label }}</span>
                        <select wire:model="columnMap.{{ $column->name }}" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">—</option>
                            @foreach ($headers as $header)
                                <option value="{{ $header }}">{{ $header }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>

            <div class="mt-6 flex justify-between">
                <x-filament::button color="gray" wire:click="startOver">
                    {{ __('panel.data_transfer.import.actions.start_over') }}
                </x-filament::button>
                <x-filament::button wire:click="runPreview">
                    {{ __('panel.data_transfer.import.actions.validate') }}
                </x-filament::button>
            </div>
        </div>
    @endif

    @if ($step === 'preview' && $previewSummary)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.data_transfer.import.summary.total') }}</div>
                    <div class="text-2xl font-semibold">{{ $previewSummary['total'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.data_transfer.import.summary.to_create') }}</div>
                    <div class="text-2xl font-semibold">{{ $previewSummary['create'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.data_transfer.import.summary.to_update') }}</div>
                    <div class="text-2xl font-semibold">{{ $previewSummary['update'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.data_transfer.import.summary.errors') }}</div>
                    <div class="text-2xl font-semibold {{ $previewSummary['errors'] > 0 ? 'text-danger-600' : '' }}">{{ $previewSummary['errors'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.data_transfer.import.summary.duplicates') }}</div>
                    <div class="text-2xl font-semibold {{ $previewSummary['duplicates'] > 0 ? 'text-warning-600' : '' }}">{{ $previewSummary['duplicates'] }}</div>
                </div>
            </div>

            @if (count($previewErrors) > 0)
                <div class="mt-6">
                    <h3 class="text-sm font-medium">{{ __('panel.data_transfer.import.errors.title') }}</h3>
                    <ul class="mt-2 space-y-1 text-sm text-danger-600">
                        @foreach ($previewErrors as $error)
                            <li>{{ __('panel.data_transfer.import.errors.row', ['row' => $error['row']]) }}: {{ implode(' ', $error['messages']) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($previewDuplicates) > 0)
                <div class="mt-6">
                    <h3 class="text-sm font-medium">{{ __('panel.data_transfer.import.duplicates.title') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.data_transfer.import.duplicates.intro') }}</p>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($previewDuplicates as $duplicate)
                            <li>
                                <span class="font-medium">{{ __('panel.data_transfer.import.duplicates.row', ['row' => $duplicate['row']]) }}</span>
                                <ul class="ms-4 mt-1 space-y-1 text-warning-700 dark:text-warning-400">
                                    @foreach ($duplicate['candidates'] as $candidate)
                                        <li>{{ $candidate['label'] }} — {{ implode(', ', $candidate['signals']) }}</li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex justify-between">
                <x-filament::button color="gray" wire:click="startOver">
                    {{ __('panel.data_transfer.import.actions.start_over') }}
                </x-filament::button>
                <x-filament::button wire:click="confirmImport">
                    {{ __('panel.data_transfer.import.actions.confirm') }}
                </x-filament::button>
            </div>
        </div>
    @endif

    @if ($step === 'done')
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                {{ trans_choice('panel.data_transfer.import.notifications.queued', $totalRows ?? 0, ['count' => $totalRows]) }}
            </p>

            <div class="mt-6">
                <x-filament::button wire:click="startOver">
                    {{ __('panel.data_transfer.import.actions.start_new') }}
                </x-filament::button>
            </div>
        </div>
    @endif

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-sm font-medium">{{ __('panel.data_transfer.import.report.title') }}</h3>

        <table class="fi-ta-table mt-4 w-full text-start">
            <thead>
                <tr>
                    <th class="p-2 text-start">{{ __('panel.data_transfer.import.report.file') }}</th>
                    <th class="p-2 text-start">{{ __('panel.data_transfer.import.report.processed') }}</th>
                    <th class="p-2 text-start">{{ __('panel.data_transfer.import.report.successful') }}</th>
                    <th class="p-2 text-start">{{ __('panel.data_transfer.import.report.failed') }}</th>
                    <th class="p-2 text-start">{{ __('panel.data_transfer.import.report.completed_at') }}</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->recentImports() as $import)
                    <tr>
                        <td class="p-2">{{ $import->file_name }}</td>
                        <td class="p-2">{{ $import->processed_rows }}/{{ $import->total_rows }}</td>
                        <td class="p-2">{{ $import->successful_rows }}</td>
                        <td class="p-2">{{ $import->getFailedRowsCount() }}</td>
                        <td class="p-2">{{ $this->completedAtLabel($import) }}</td>
                        <td class="p-2">
                            @if ($import->getFailedRowsCount() > 0)
                                <button type="button" wire:click="toggleFailedRows({{ $import->id }})" class="text-sm text-primary-600">
                                    {{ __('panel.data_transfer.import.report.view_errors') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if ($viewingImportId === $import->id)
                        <tr>
                            <td colspan="6" class="p-2">
                                <ul class="space-y-1 text-sm text-danger-600">
                                    @foreach ($this->viewingFailedRows() as $failedRow)
                                        <li>{{ $failedRow->validation_error }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
