<x-layouts.public :metadata="$metadata" :breadcrumbs="$breadcrumbs" :structured-data="$structuredData">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        @php
            // Figma's own gallery (node 225:3111) is one hero beside a 2x2
            // mosaic of supporting photos, all visible without scrolling —
            // not the single cover plus a horizontal thumbnail strip this
            // page rendered before. Degrades by hiding unfilled thumbnail
            // cells rather than stretching the hero to fill them, and caps
            // the mosaic at four supporting photos with a "+N" overlay on
            // the last one — a fifth-and-beyond photo belongs to the full
            // gallery a future lightbox opens, not squeezed into this block.
            $mosaicPhotos = array_slice($profile->galleryPhotoUrls, 0, 4);
            $remainingPhotoCount = max(0, count($profile->galleryPhotoUrls) - count($mosaicPhotos));
        @endphp

        {{-- Cover + up to four supporting photos, mosaic — placement and availability badges sit on the hero --}}
        <div class="grid h-72 grid-cols-1 grid-rows-1 gap-2 overflow-hidden rounded-lg sm:h-96 sm:grid-cols-4 sm:grid-rows-2">
            <div class="relative sm:col-span-2 sm:row-span-2">
                @if ($profile->coverPhotoUrl)
                    <img
                        src="{{ $profile->coverPhotoUrl }}"
                        alt="{{ $profile->name }}"
                        class="h-full w-full object-cover"
                    >
                @else
                    <div class="flex h-full w-full items-center justify-center bg-gray-200 text-gray-400">
                        <x-public.icons.camera class="h-12 w-12" />
                    </div>
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

            @foreach ($mosaicPhotos as $index => $photoUrl)
                <div class="relative hidden sm:block">
                    <img
                        src="{{ $photoUrl }}"
                        alt="{{ $profile->name }}"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    >
                    @if ($index === count($mosaicPhotos) - 1 && $remainingPhotoCount > 0)
                        <div class="absolute inset-0 flex items-center justify-center bg-black/50 text-lg font-medium text-white">
                            +{{ $remainingPhotoCount }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- The same photos, as a scrollable strip — the mosaic above hides on
             mobile and caps at four on desktop; this keeps every photo reachable
             regardless of viewport, until a full gallery lightbox replaces both. --}}
        @if (count($profile->galleryPhotoUrls) > 0)
            <div class="mt-3 flex gap-3 overflow-x-auto pb-2 sm:hidden">
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
                <h1 class="text-3xl font-medium text-brand">{{ $profile->name }}</h1>
                <p class="mt-1 text-ink-muted">
                    @if ($profile->categoryName)
                        {{ $profile->categoryName }} ·
                    @endif
                    {{ $profile->typeName }} · {{ $profile->settlement }}
                </p>
            </div>

            <x-public.star-rating :average="$profile->ratingAverage" :count="$profile->reviewCount" />
        </div>

        {{-- Contact rail — the page's own conversion element, kept above
             the fold rather than buried below the description. --}}
        @if (count($profile->contactActions) > 0)
            <div class="sticky top-4 z-10 mt-4 flex flex-wrap gap-2 bg-white py-2">
                @foreach ($profile->contactActions as $action)
                    <a
                        href="{{ $action->href }}"
                        class="rounded-full border border-brand px-4 py-2 text-sm font-medium text-brand hover:bg-brand hover:text-white"
                    >
                        {{ $action->label }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Short description --}}
        @if ($profile->shortDescription)
            <p class="mt-4 max-w-3xl text-lg text-ink">{{ $profile->shortDescription }}</p>
        @endif

        @if ($bannerTop)
            <div class="mt-6">
                <a href="{{ route('banners.click', ['banner' => $bannerTop->id]) }}">
                    <x-public.banner-creative :banner="$bannerTop" class="w-full rounded-lg" />
                </a>
            </div>
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
                <x-public.section-heading>{{ __('public.object.rooms_heading') }}</x-public.section-heading>
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
                                    <dd class="flex items-center gap-1">
                                        <x-public.icons.guest class="h-4 w-4 shrink-0" />
                                        {{ $room['maxGuests'] }}
                                    </dd>
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
                <x-public.section-heading>{{ __('public.object.prices_heading') }}</x-public.section-heading>
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
                <x-public.section-heading>{{ __('public.object.details_heading') }}</x-public.section-heading>
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
                <x-public.section-heading>{{ __('public.object.services_heading') }}</x-public.section-heading>
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

        {{-- Location: address, map, directions --}}
        @if ($object->latitude !== null && $object->longitude !== null)
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.object.location_heading') }}</x-public.section-heading>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                    @if ($object->address)
                        <p class="text-ink">{{ $object->address }}</p>
                    @endif
                    <a
                        href="https://www.google.com/maps/dir/?api=1&destination={{ $object->latitude }},{{ $object->longitude }}"
                        target="_blank"
                        rel="noopener"
                        class="text-sm font-medium text-brand hover:underline"
                    >
                        {{ __('public.object.directions_link') }}
                    </a>
                </div>
                <div class="mt-4">
                    <x-public.map
                        :center-lat="(float) $object->latitude"
                        :center-lng="(float) $object->longitude"
                        :zoom="15"
                    />
                </div>
            </section>
        @endif

        {{-- Object promotions --}}
        @if (count($profile->objectPromotions) > 0)
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.territory.promotions') }}</x-public.section-heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($profile->objectPromotions as $promotion)
                        <x-public.content-card
                            :title="$promotion->title"
                            :summary="$promotion->summary"
                            :href="\Illuminate\Support\Facades\Route::has('public.promotions.show') ? route('public.promotions.show', ['lang' => app()->getLocale(), 'slug' => $promotion->slug]) : null"
                            :cover-image-url="$promotion->getFirstMediaUrl('image') ?: null"
                        />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Object news --}}
        @if (count($profile->objectNews) > 0)
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.shell.nav.news') }}</x-public.section-heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($profile->objectNews as $newsItem)
                        <x-public.content-card
                            :title="$newsItem->title"
                            :summary="$newsItem->summary"
                            :href="\Illuminate\Support\Facades\Route::has('public.news.show') ? route('public.news.show', ['lang' => app()->getLocale(), 'slug' => $newsItem->slug]) : null"
                            :cover-image-url="$newsItem->getFirstMediaUrl('cover_image') ?: null"
                        />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Reviews: aggregate (in the header above) + itemized + owner
             replies — omitted entirely (never an empty block) whenever
             there is nothing published to show, whether that is because
             no review exists yet or because the reviews module resolves
             disabled for this object's own scope. --}}
        @if (count($profile->reviews) > 0)
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.object.reviews.heading') }}</x-public.section-heading>
                <div class="mt-4 flex flex-col gap-4">
                    @foreach ($profile->reviews as $review)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-ink">{{ $review['authorName'] }}</span>
                                <span class="text-sm text-ink-muted">{{ $review['date'] }}</span>
                            </div>
                            <div class="mt-1 text-sm font-semibold text-brand">{{ $review['rating'] }} / 5</div>
                            <p class="mt-2 text-ink">{{ $review['body'] }}</p>

                            @if ($review['ownerReply'])
                                <div class="mt-3 rounded-lg bg-surface-muted p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-medium text-ink">{{ __('public.object.reviews.owner_reply') }}</span>
                                        <span class="text-xs text-ink-muted">{{ $review['ownerReplyDate'] }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-ink">{{ $review['ownerReply'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Review submission form — reachable per reviews.submission_mode
             (ReviewSubmissionGate): always in `open` mode (a CAPTCHA
             challenge is the control there instead), or only after a
             contact-channel click for this object, this session, in
             `contact_gated` mode. Enforced again server-side by
             ReviewSubmissionController regardless of what renders here. --}}
        <section class="mt-10">
            @if (session('public-review-submitted'))
                <p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ __('public.object.reviews.form.thanks') }}</p>
            @elseif ($reviewForm->mode === 'contact_gated' && ! $reviewForm->canSubmit)
                <p class="rounded-lg bg-surface-muted p-4 text-sm text-ink-muted">{{ __('public.object.reviews.form.contact_first') }}</p>
            @else
                <x-public.section-heading>{{ __('public.object.reviews.form.heading') }}</x-public.section-heading>

                @error('review')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('public.objects.reviews.submit', ['lang' => app()->getLocale(), 'object' => $object->id]) }}" class="mt-4 flex flex-col gap-3">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-ink" for="review-author-name">{{ __('public.object.reviews.form.name') }}</label>
                        <input id="review-author-name" name="author_name" type="text" required value="{{ old('author_name') }}" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink" for="review-rating">{{ __('public.object.reviews.form.rating') }}</label>
                        <select id="review-rating" name="rating" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            @for ($stars = 5; $stars >= 1; $stars--)
                                <option value="{{ $stars }}" @selected(old('rating') == $stars)>{{ $stars }} / 5</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink" for="review-body">{{ __('public.object.reviews.form.body') }}</label>
                        <textarea id="review-body" name="body" rows="4" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>
                    </div>

                    @if ($reviewForm->mode === 'open' && $reviewForm->captchaEnabled)
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                        <div class="cf-turnstile" data-sitekey="{{ $reviewForm->captchaSiteKey }}"></div>
                    @endif

                    <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                        {{ __('public.object.reviews.form.submit') }}
                    </button>
                </form>
            @endif
        </section>

        {{-- Nearby objects — the object's own territory, tier-ordered
             through CatalogQueryService like every other listing surface. --}}
        @if (count($profile->nearbyObjects) > 0)
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.object.nearby_heading') }}</x-public.section-heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($profile->nearbyObjects as $nearbyObject)
                        <x-object-card :object="$nearbyObject" variant="tile" wire:key="nearby-card-{{ $nearbyObject->id }}" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Similar objects — same type, country-wide, tier-ordered. --}}
        @if (count($profile->similarObjects) > 0)
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.object.similar_heading') }}</x-public.section-heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($profile->similarObjects as $similarObject)
                        <x-object-card :object="$similarObject" variant="tile" wire:key="similar-card-{{ $similarObject->id }}" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
