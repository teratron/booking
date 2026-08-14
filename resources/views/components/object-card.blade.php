{{--
    The single result-card component every public listing surface renders.
    Card geometry (outer size and structure) never changes between tiers —
    only the border colour and the ribbon badge do.
--}}
<div
    class="flex w-full flex-col overflow-hidden rounded-lg bg-surface-muted shadow-sm sm:flex-row"
    style="border: 2px solid {{ $card->tierBorderColour ?? 'transparent' }}"
    data-object-card-id="{{ $card->objectId }}"
>
    <div class="relative h-56 w-full shrink-0 overflow-hidden sm:h-auto sm:w-72">
        @if ($card->coverPhotoUrl)
            <img
                src="{{ $card->coverPhotoUrl }}"
                alt="{{ $card->name }}"
                class="h-full w-full object-cover"
                loading="lazy"
            >
        @else
            <div class="flex h-full w-full items-center justify-center bg-gray-200 text-gray-400"></div>
        @endif

        @if ($card->tierBadgeText)
            <span
                class="absolute left-0 top-3 rounded-r px-3 py-1 text-sm font-medium text-white"
                style="background-color: {{ $card->tierBadgeColour }}"
            >
                {{ $card->tierBadgeText }}
            </span>
        @endif

        @if ($card->availabilityStatus)
            <span
                @class([
                    'absolute bottom-2 right-2 rounded px-2 py-1 text-xs font-medium text-white',
                    'bg-emerald-600' => $card->availabilityStatus === 'available',
                    'bg-rose-600' => $card->availabilityStatus === 'unavailable',
                    'bg-gray-500' => $card->availabilityStatus === 'unspecified',
                ])
            >
                {{ __('public.catalog.card.availability.'.$card->availabilityStatus) }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                @if ($card->detailsUrl)
                    <a href="{{ $card->detailsUrl }}" class="text-xl font-semibold text-brand hover:underline">
                        {{ $card->name }}
                    </a>
                @else
                    <span class="text-xl font-semibold text-brand">{{ $card->name }}</span>
                @endif

                @if ($card->settlement !== '')
                    <p class="text-sm text-ink">{{ $card->settlement }}</p>
                @endif
            </div>

            @if ($card->reviewCount > 0)
                <div class="shrink-0 text-right">
                    <div class="text-lg font-semibold text-ink">{{ number_format((float) $card->ratingAverage, 1) }} / 5</div>
                    <div class="text-xs text-ink-muted">{{ trans_choice('public.catalog.card.reviews', $card->reviewCount) }}</div>
                </div>
            @endif
        </div>

        @if ($card->shortDescription)
            <p class="line-clamp-2 text-sm text-ink">{{ $card->shortDescription }}</p>
        @endif

        @if (count($card->keyServices) > 0)
            <div class="flex flex-wrap gap-2">
                @foreach ($card->keyServices as $service)
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded border border-brand bg-brand/10"
                        title="{{ $service['label'] }}"
                    >
                        @if ($service['iconPath'])
                            <img
                                src="{{ Illuminate\Support\Facades\Storage::url($service['iconPath']) }}"
                                alt="{{ $service['label'] }}"
                                class="h-4 w-4"
                            >
                        @endif
                    </span>
                @endforeach
            </div>
        @endif

        <div class="mt-auto flex flex-wrap items-end justify-between gap-3 pt-2">
            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">{{ trans_choice('public.catalog.card.views', $card->viewCount) }}</span>

                @if ($card->priceFromAmount)
                    <div class="text-sm text-ink">
                        {{ __('public.catalog.card.price_from') }}
                        <span class="font-semibold text-brand">{{ $card->priceFromAmount }}</span>
                        {{ $card->priceCurrency }}
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @foreach ($card->contactActions as $action)
                    <a
                        href="{{ $action->href }}"
                        class="rounded-full border border-brand px-3 py-1 text-xs font-medium text-brand hover:bg-brand hover:text-white"
                    >
                        {{ $action->label }}
                    </a>
                @endforeach

                @if ($card->detailsUrl)
                    <a
                        href="{{ $card->detailsUrl }}"
                        class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:opacity-90"
                    >
                        {{ __('public.catalog.card.details') }}
                    </a>
                @else
                    <span class="cursor-not-allowed rounded-lg bg-gray-300 px-4 py-2 text-sm font-medium text-gray-500">
                        {{ __('public.catalog.card.details') }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
