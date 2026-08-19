<x-filament-panels::page>
    @php
        $backups = $this->availableBackups();
        $selected = $this->selectedBackup();
    @endphp

    <div class="fi-section rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/20">
        <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
            {{ __('panel.backup_restore.warning') }}
        </p>
    </div>

    {{-- Step one: select the artefact. --}}
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold">{{ __('panel.backup_restore.steps.select.title') }}</h3>

        @if ($backups->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_restore.none') }}</p>
        @else
            <table class="fi-ta-table mt-2 w-full text-start">
                <thead>
                    <tr>
                        <th class="p-2 text-start">{{ __('panel.backup_restore.columns.date') }}</th>
                        <th class="p-2 text-start">{{ __('panel.backup_restore.columns.size') }}</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr>
                            <td class="p-2">{{ $backup->date()->toDateTimeString() }}</td>
                            <td class="p-2">{{ $this->backupSizeLabel($backup) }}</td>
                            <td class="p-2 text-end">
                                <x-filament::button
                                    size="sm"
                                    :color="$selected && $selected->path() === $backup->path() ? 'primary' : 'gray'"
                                    wire:click="selectBackup('{{ $backup->path() }}')"
                                >
                                    {{ $selected && $selected->path() === $backup->path()
                                        ? __('panel.backup_restore.actions.selected')
                                        : __('panel.backup_restore.actions.select') }}
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Step two: confirm what the selected artefact overwrites. --}}
    @if ($selected)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold">{{ __('panel.backup_restore.steps.confirm.title') }}</h3>

            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                {{ $this->confirmationMessage() }}
            </p>

            @if ($isConfirmed)
                <p class="mt-4 text-sm font-medium text-success-600">
                    {{ __('panel.backup_restore.steps.confirm.confirmed') }}
                </p>
            @else
                <div class="mt-4">
                    <x-filament::button color="danger" wire:click="confirmSelection">
                        {{ __('panel.backup_restore.actions.confirm') }}
                    </x-filament::button>
                </div>
            @endif
        </div>
    @endif

    {{-- Step three (re-authentication) renders as the page's own header action, above, once steps one and two are both satisfied. --}}
</x-filament-panels::page>
