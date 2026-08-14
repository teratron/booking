{{--
    The compact card a map pin opens on click. Deliberately smaller than
    the full list card, but built from the exact same view model — its
    contact actions are the same actions the list card renders, not a
    second resolution.
--}}
@props(['card'])
<div class="flex w-64 gap-3 p-1" data-map-pin-card-id="{{ $card->objectId }}">
    <div class="h-16 w-16 shrink-0 overflow-hidden rounded bg-gray-200">
        @if ($card->coverPhotoUrl)
            <img src="{{ $card->coverPhotoUrl }}" alt="{{ $card->name }}" class="h-full w-full object-cover">
        @endif
    </div>

    <div class="flex min-w-0 flex-1 flex-col gap-1">
        @if ($card->detailsUrl)
            <a href="{{ $card->detailsUrl }}" class="truncate text-sm font-semibold text-brand hover:underline">
                {{ $card->name }}
            </a>
        @else
            <span class="truncate text-sm font-semibold text-brand">{{ $card->name }}</span>
        @endif

        @if ($card->priceFromAmount)
            <p class="text-xs text-ink">
                {{ __('public.catalog.card.price_from') }}
                <span class="font-semibold text-brand">{{ $card->priceFromAmount }}</span>
                {{ $card->priceCurrency }}
            </p>
        @endif

        @if (count($card->contactActions) > 0)
            <div class="flex flex-wrap gap-1">
                @foreach ($card->contactActions as $action)
                    <a
                        href="{{ $action->href }}"
                        class="rounded-full border border-brand px-2 py-0.5 text-[11px] font-medium text-brand hover:bg-brand hover:text-white"
                    >
                        {{ $action->label }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
