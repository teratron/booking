<x-filament-panels::page>
    @php
        // Computed together, in this order, before anything renders: every
        // one reads through the same memoized service instance, so
        // $unreachable (computed last) reflects whether *any* of the reads
        // above it failed, not only whichever ran first.
        $lastDatabaseBackup = $this->lastDatabaseBackup();
        $lastMediaDate = $this->lastMediaBackupDate();
        $isStale = $this->isDatabaseBackupStale();
        $databaseHistory = $this->databaseBackupHistory();
        $mediaHistory = $this->mediaGenerationHistory();
        $unreachable = $this->destinationUnreachable();
    @endphp

    @if ($unreachable)
        <div class="fi-section rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/20">
            <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
                {{ __('panel.backup_administration.destination_unreachable') }}
            </p>
        </div>
    @endif

    @if ($isStale)
        <div class="fi-section rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/20">
            <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
                {{ __('panel.backup_administration.staleness_warning', ['hours' => $this->stalenessThresholdHours()]) }}
            </p>
        </div>
    @endif

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_administration.fields.last_database_backup') }}</div>
                <div class="text-lg font-semibold {{ $isStale ? 'text-danger-600' : '' }}">
                    @if ($lastDatabaseBackup && $lastDatabaseBackup->exists())
                        {{ $lastDatabaseBackup->date()->toDateTimeString() }}
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $lastDatabaseBackup->date()->diffForHumans() }})</span>
                    @else
                        {{ __('panel.backup_administration.none') }}
                    @endif
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_administration.fields.last_media_backup') }}</div>
                <div class="text-lg font-semibold">
                    @if ($lastMediaDate)
                        {{ $lastMediaDate->toDateTimeString() }}
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $lastMediaDate->diffForHumans() }})</span>
                    @else
                        {{ __('panel.backup_administration.none') }}
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button wire:click="runBackupNow">
                {{ __('panel.backup_administration.actions.run_now') }}
            </x-filament::button>

            <x-filament::button color="gray" wire:click="downloadTechnicalReport">
                {{ __('panel.backup_administration.actions.download_report') }}
            </x-filament::button>
        </div>
    </div>

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold">{{ __('panel.backup_administration.log.database_title') }}</h3>

        @if ($databaseHistory->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_administration.none') }}</p>
        @else
            <table class="fi-ta-table mt-2 w-full text-start">
                <thead>
                    <tr>
                        <th class="p-2 text-start">{{ __('panel.backup_administration.log.columns.date') }}</th>
                        <th class="p-2 text-start">{{ __('panel.backup_administration.log.columns.size') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($databaseHistory as $backup)
                        <tr>
                            <td class="p-2">{{ $backup->date()->toDateTimeString() }}</td>
                            <td class="p-2">{{ $this->backupSizeLabel($backup) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold">{{ __('panel.backup_administration.log.media_title') }}</h3>

        @if ($mediaHistory->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_administration.none') }}</p>
        @else
            <table class="fi-ta-table mt-2 w-full text-start">
                <thead>
                    <tr>
                        <th class="p-2 text-start">{{ __('panel.backup_administration.log.columns.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mediaHistory as $generation)
                        <tr>
                            <td class="p-2">{{ $generation['date']->toDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
