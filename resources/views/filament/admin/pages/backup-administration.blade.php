<x-filament-panels::page>
    @php
        // One cached snapshot (15-minute TTL) rather than a live
        // destination-disk enumeration on every render — see
        // BackupAdministrationService::viewSnapshot().
        $snapshot = $this->snapshot();
        $lastDb = $snapshot['last_database_backup_at'] ? \Illuminate\Support\Carbon::parse($snapshot['last_database_backup_at']) : null;
        $lastMedia = $snapshot['last_media_backup_at'] ? \Illuminate\Support\Carbon::parse($snapshot['last_media_backup_at']) : null;
        $generatedAt = \Illuminate\Support\Carbon::parse($snapshot['generated_at']);
    @endphp

    @if ($snapshot['unreachable'])
        <div class="fi-section rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/20">
            <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
                {{ __('panel.backup_administration.destination_unreachable') }}
            </p>
        </div>
    @endif

    @if ($snapshot['is_stale'])
        <div class="fi-section rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/20">
            <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
                {{ __('panel.backup_administration.staleness_warning', ['hours' => $snapshot['staleness_threshold_hours']]) }}
            </p>
        </div>
    @endif

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_administration.fields.last_database_backup') }}</div>
                <div class="text-lg font-semibold {{ $snapshot['is_stale'] ? 'text-danger-600' : '' }}">
                    @if ($lastDb)
                        {{ $lastDb->toDateTimeString() }}
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $lastDb->diffForHumans() }})</span>
                    @else
                        {{ __('panel.backup_administration.none') }}
                    @endif
                </div>
            </div>

            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_administration.fields.last_media_backup') }}</div>
                <div class="text-lg font-semibold">
                    @if ($lastMedia)
                        {{ $lastMedia->toDateTimeString() }}
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $lastMedia->diffForHumans() }})</span>
                    @else
                        {{ __('panel.backup_administration.none') }}
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-filament::button wire:click="runBackupNow">
                {{ __('panel.backup_administration.actions.run_now') }}
            </x-filament::button>

            <x-filament::button color="gray" wire:click="downloadTechnicalReport">
                {{ __('panel.backup_administration.actions.download_report') }}
            </x-filament::button>

            <x-filament::button color="gray" wire:click="recheckNow">
                {{ __('panel.backup_administration.actions.recheck_now') }}
            </x-filament::button>

            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('panel.backup_administration.checked_at', ['ago' => $generatedAt->diffForHumans()]) }}
            </span>
        </div>
    </div>

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold">{{ __('panel.backup_administration.log.database_title') }}</h3>

        @if (empty($snapshot['database_history']))
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
                    @foreach ($snapshot['database_history'] as $backup)
                        <tr>
                            <td class="p-2">{{ \Illuminate\Support\Carbon::parse($backup['date'])->toDateTimeString() }}</td>
                            <td class="p-2">{{ $backup['size'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold">{{ __('panel.backup_administration.log.media_title') }}</h3>

        @if (empty($snapshot['media_history']))
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.backup_administration.none') }}</p>
        @else
            <table class="fi-ta-table mt-2 w-full text-start">
                <thead>
                    <tr>
                        <th class="p-2 text-start">{{ __('panel.backup_administration.log.columns.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($snapshot['media_history'] as $generation)
                        <tr>
                            <td class="p-2">{{ \Illuminate\Support\Carbon::parse($generation['date'])->toDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
