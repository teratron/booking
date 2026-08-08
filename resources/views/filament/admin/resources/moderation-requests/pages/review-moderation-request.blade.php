<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="fi-ta-table w-full text-start">
            <thead>
                <tr>
                    <th class="p-2 text-start">{{ __('panel.moderation_review.columns.field') }}</th>
                    <th class="p-2 text-start">{{ __('panel.moderation_review.columns.published') }}</th>
                    <th class="p-2 text-start">{{ __('panel.moderation_review.columns.proposed') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->diff() as $row)
                    <tr @class(['bg-warning-50 dark:bg-warning-950/40' => $row['changed']])>
                        <td class="p-2 font-medium">{{ $row['key'] }}</td>
                        <td class="p-2">
                            @if ($this->isImageValue($row['previous']))
                                <img src="{{ $row['previous'] }}" alt="" class="h-24 w-auto rounded" />
                            @else
                                {{ $row['previous'] ?? '—' }}
                            @endif
                        </td>
                        <td class="p-2">
                            @if ($this->isImageValue($row['proposed']))
                                <img src="{{ $row['proposed'] }}" alt="" class="h-24 w-auto rounded" />
                            @else
                                {{ $row['proposed'] ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
