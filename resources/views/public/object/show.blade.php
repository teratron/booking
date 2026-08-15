<x-layouts.public :title="$profile->name" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        {{-- Cover + placement badge + availability badge --}}
        <div class="relative h-72 w-full overflow-hidden rounded-lg sm:h-96">
            @if ($profile->coverPhotoUrl)
                <img
                    src="{{ $profile->coverPhotoUrl }}"
                    alt="{{ $profile->name }}"
                    class="h-full w-full object-cover"
                >
            @else
                <div class="flex h-full w-full items-center justify-center bg-gray-200 text-gray-400"></div>
            @endif

            @if ($profile->tierBadgeText)
                <span
                    class="absolute left-0 top-4 rounded-r px-3 py-1 text-sm font-medium text-white"
                    style="background-color: {{ $profile->tierBadgeColour }}"
                >
                    {{ $profile->tierBadgeText }}
                </span>
            @endif

            @if ($profile->availabilityStatus === 'available')
                <span class="absolute bottom-3 right-3 rounded bg-emerald-600 px-3 py-1 text-sm font-medium text-white">
                    {{ __('public.catalog.card.availability.available') }}
                </span>
            @endif
        </div>

        {{-- Gallery (remaining photos) --}}
        @if (count($profile->galleryPhotoUrls) > 0)
            <div class="mt-3 flex gap-3 overflow-x-auto pb-2">
                @foreach ($profile->galleryPhotoUrls as $photoUrl)
                    <img
                        src="{{ $photoUrl }}"
                        alt="{{ $profile->name }}"
                        class="h-24 w-32 shrink-0 rounded-lg object-cover"
                        loading="lazy"
                    >
                @endforeach
            </div>
        @endif

        {{-- Name · type · category · rating · settlement --}}
        <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold text-brand">{{ $profile->name }}</h1>
                <p class="mt-1 text-ink-muted">
                    @if ($profile->categoryName)
                        {{ $profile->categoryName }} ·
                    @endif
                    {{ $profile->typeName }} · {{ $profile->settlement }}
                </p>
            </div>

            @if ($profile->reviewCount > 0)
                <div class="shrink-0 text-right">
                    <div class="text-xl font-semibold text-ink">{{ number_format((float) $profile->ratingAverage, 1) }} / 5</div>
                    <div class="text-sm text-ink-muted">{{ trans_choice('public.catalog.card.reviews', $profile->reviewCount) }}</div>
                </div>
            @endif
        </div>

        {{-- Short description --}}
        @if ($profile->shortDescription)
            <p class="mt-4 max-w-3xl text-lg text-ink">{{ $profile->shortDescription }}</p>
        @endif

        {{-- Full description --}}
        @if ($profile->fullDescription)
            <section class="mt-4 max-w-3xl text-ink">
                {{ $profile->fullDescription }}
            </section>
        @endif

        {{-- Type-specific block: rooms (accommodation) --}}
        @if ($profile->hasRooms && count($profile->rooms) > 0)
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.object.rooms_heading') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($profile->rooms as $room)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="font-medium text-ink">{{ $room['name'] }}</p>

                            @if ($room['description'])
                                <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ $room['description'] }}</p>
                            @endif

                            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-sm text-ink-muted">
                                @if ($room['maxGuests'])
                                    <dt>{{ __('public.object.room.max_guests') }}</dt>
                                    <dd>{{ $room['maxGuests'] }}</dd>
                                @endif
                                @if ($room['areaSqm'])
                                    <dt>{{ __('public.object.room.area') }}</dt>
                                    <dd>{{ $room['areaSqm'] }} m²</dd>
                                @endif
                                @if ($room['bedConfiguration'])
                                    <dt>{{ __('public.object.room.capacity') }}</dt>
                                    <dd>{{ $room['bedConfiguration'] }}</dd>
                                @endif
                                @if ($room['hasExtraBed'])
                                    <dt class="col-span-2">{{ __('public.object.room.extra_bed') }}</dt>
                                @endif
                            </dl>

                            @if (count($room['amenities']) > 0)
                                <p class="mt-3 text-xs text-ink-muted">{{ implode(' · ', $room['amenities']) }}</p>
                            @endif

                            @if (count($room['prices']) > 0)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($room['prices'] as $price)
                                        <span class="rounded-full bg-brand/10 px-3 py-1 text-sm text-brand">
                                            {{ $price['amount'] }} {{ $price['currency'] }}
                                            {{ __('public.object.price.calculation_unit.'.$price['unit']) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Type-specific block: prices (non-accommodation, e.g. an average cheque) --}}
        @if (! $profile->hasRooms && count($profile->objectPrices) > 0)
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.object.prices_heading') }}</h2>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($profile->objectPrices as $price)
                        <span class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-ink">
                            @if ($price['label'])
                                <span class="font-medium">{{ $price['label'] }}:</span>
                            @endif
                            {{ $price['amount'] }} {{ $price['currency'] }}
                            {{ __('public.object.price.calculation_unit.'.$price['unit']) }}
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Type-varying details (catering, house rules, cuisine, opening hours, visiting information — read uniformly from the type's own declared attribute schema) --}}
        @if (count($profile->attributes) > 0)
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.object.details_heading') }}</h2>
                <dl class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                    @foreach ($profile->attributes as $attribute)
                        <div class="flex justify-between border-b border-gray-100 py-2 sm:justify-start sm:gap-3">
                            <dt class="text-ink-muted">{{ $attribute['label'] }}</dt>
                            <dd class="text-ink">{{ $attribute['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        {{-- Services & infrastructure (grouped, icon-tagged) --}}
        @if (count($profile->amenityGroups) > 0)
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.object.services_heading') }}</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($profile->amenityGroups as $group)
                        <div>
                            @if ($group['groupName'])
                                <p class="text-sm font-medium text-ink-muted">{{ $group['groupName'] }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($group['amenities'] as $amenity)
                                    <span
                                        class="flex items-center gap-2 rounded-full border border-brand bg-brand/10 px-3 py-1 text-sm text-ink"
                                        title="{{ $amenity['label'] }}"
                                    >
                                        @if ($amenity['iconPath'])
                                            <img
                                                src="{{ Illuminate\Support\Facades\Storage::url($amenity['iconPath']) }}"
                                                alt=""
                                                class="h-4 w-4"
                                            >
                                        @endif
                                        {{ $amenity['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
