<x-filament-panels::page>
    <div class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.broadcast.quota_remaining', ['count' => $this->remainingQuota()]) }}
    </div>

    {{ $this->form }}
</x-filament-panels::page>
