{{--
    The single result-card component every public listing surface renders,
    in two geometries sharing one visual language (tier border colour,
    badge, rating, price, contact actions):

    - `row` (default) — full-width, image beside content. Matches the Figma
      source exactly (Booking file, node 225:3813 "карточка отеля1") and is
      correct wherever the card is the only thing in its row: the catalog's
      own list view, the object page's "similar objects" list.
    - `tile` — image over content, sized for a narrower grid cell. Figma's
      catalog frame shows only the list geometry — the grid/list toggle and
      the home page's card rails are both capability this project added
      beyond the source design — so there is no tile node to match; this
      variant is designed to the same type scale and spacing the row
      variant already uses, not invented from scratch.

    Before this split, every grid placement (the home page's "Рекомендуем"/
    "Новые объекты" rails, the catalog's own tile toggle) used the row
    geometry unconditionally: a 288px fixed-width image plus its `sm:flex-row`
    layout activated at any viewport width, regardless of how narrow the
    actual grid cell was, clipping titles and cutting off the "Подробнее"
    button. Tailwind breakpoints key off the viewport, not the container, so
    the row geometry had no way to know it had been placed somewhere narrow.
--}}
@props(['variant' => 'row'])
@php
    $isTile = $variant === 'tile';
@endphp
<div
    @class([
        'flex overflow-hidden rounded-lg bg-surface-muted shadow-card',
        'w-full flex-col' => $isTile,
        'w-full flex-col sm:flex-row' => ! $isTile,
    ])
    style="border: 2px solid {{ $card->tierBorderColour ?? 'transparent' }}"
    data-object-card-id="{{ $card->objectId }}"
>
    <div
        @class([
            'relative shrink-0 overflow-hidden',
            'h-48 w-full' => $isTile,
            'h-56 w-full sm:h-auto sm:w-72' => ! $isTile,
        ])
    >
        @if ($card->coverPhotoUrl)
            <img
                src="{{ $card->coverPhotoUrl }}"
                alt="{{ $card->name }}"
                class="h-full w-full object-cover"
                loading="lazy"
            >
        @else
            <div class="flex h-full w-full items-center justify-center bg-gray-200 text-gray-400">
                <x-public.icons.camera class="h-10 w-10" />
            </div>
        @endif

        @if ($card->tierBadgeText)
            <span
                class="absolute left-0 top-3 rounded-r px-3 py-1 text-sm font-medium text-white"
                style="background-color: {{ $card->tierBadgeColour }}"
            >
                {{ $card->tierBadgeText }}
            </span>
        @endif

        {{-- Only the positive state ever renders a badge — a false
             "available" is recoverable by a phone call, a false "no
             vacancies" is not, so the negative and unspecified states
             render nothing at all. --}}
        @if ($card->availabilityStatus === 'available')
            <span class="absolute bottom-2 right-2 rounded bg-emerald-600 px-2 py-1 text-xs font-medium text-white">
                {{ __('public.catalog.card.availability.available') }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                @if ($card->detailsUrl)
                    <a href="{{ $card->detailsUrl }}" @class(['font-medium text-brand hover:underline', 'text-xl' => $isTile, 'text-2xl sm:text-3xl' => ! $isTile])>
                        {{ $card->name }}
                    </a>
                @else
                    <span @class(['font-medium text-brand', 'text-xl' => $isTile, 'text-2xl sm:text-3xl' => ! $isTile])>{{ $card->name }}</span>
                @endif

                @if ($card->settlement !== '')
                    <p @class(['font-medium text-ink', 'text-base' => $isTile, 'text-lg' => ! $isTile])>{{ $card->settlement }}</p>
                @endif
            </div>

            <x-public.star-rating :average="$card->ratingAverage" :count="$card->reviewCount" />
        </div>

        @if ($card->shortDescription)
            <p class="line-clamp-2 text-base text-ink">{{ $card->shortDescription }}</p>
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

        <div @class(['mt-auto flex gap-3 pt-2', 'flex-col items-stretch' => $isTile, 'flex-wrap items-end justify-between' => ! $isTile])>
            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">{{ trans_choice('public.catalog.card.views', $card->viewCount) }}</span>

                @if ($card->priceFromAmount)
                    <div class="text-lg text-ink">
                        {{ __('public.catalog.card.price_from') }}
                        <span class="font-medium text-brand">{{ $card->priceFromAmount }}</span>
                        {{ $card->priceCurrency }}
                    </div>
                @endif
            </div>

            <div @class(['flex flex-wrap items-center gap-2', 'justify-between' => $isTile])>
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
                        @class(['rounded-lg bg-brand text-center font-medium text-white hover:opacity-90', 'flex-1 px-4 py-2 text-sm' => $isTile, 'px-6 py-2.5 text-base' => ! $isTile])
                    >
                        {{ __('public.catalog.card.details') }}
                    </a>
                @else
                    <span @class(['cursor-not-allowed rounded-lg bg-gray-300 text-center font-medium text-gray-500', 'flex-1 px-4 py-2 text-sm' => $isTile, 'px-6 py-2.5 text-base' => ! $isTile])>
                        {{ __('public.catalog.card.details') }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
