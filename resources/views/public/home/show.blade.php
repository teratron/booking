<x-layouts.public>
    {{--
        Sixteen blocks in §5.2 composition order (header/footer are the
        shared layout's own blocks 1 and 16). Every block below is omitted
        entirely — never an empty frame — when it has nothing to show.
    --}}

    {{-- 2. Hero search --}}
    <section class="bg-brand py-12 text-center text-white">
        <h1 class="text-3xl font-semibold">{{ __('public.home.hero.title') }}</h1>
        <p class="mt-2 text-white/90">{{ __('public.home.hero.subtitle') }}</p>
        <div class="mx-auto mt-6 max-w-2xl px-4">
            <x-public.search-form />
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-10 lg:px-8">
        {{-- 3. Banner slot (top) --}}
        @if ($bannerTop)
            <section class="mb-10">
                <a href="{{ route('banners.click', ['banner' => $bannerTop->id]) }}">
                    <img src="{{ $bannerTop->getFirstMediaUrl('desktop_creative') }}" alt="{{ $bannerTop->link_text }}" class="w-full rounded-lg">
                </a>
            </section>
        @endif

        {{-- Popular destinations --}}
        @if (count($popularDestinations) > 0)
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.territory.explore') }}</h2>
                <div class="mt-4 flex gap-3 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 sm:overflow-visible lg:grid-cols-4">
                    @foreach ($popularDestinations as $destination)
                        <a
                            href="{{ $destination->url }}"
                            class="w-48 shrink-0 rounded-lg border border-gray-200 p-4 text-center hover:border-brand sm:w-auto"
                        >
                            {{ $destination->name }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Object categories --}}
        @if (count($categoryTiles) > 0)
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.home.categories_heading') }}</h2>
                <div class="mt-4 flex gap-3 overflow-x-auto pb-2 sm:grid sm:grid-cols-3 sm:overflow-visible lg:grid-cols-6">
                    @foreach ($categoryTiles as $tile)
                        <x-public.nav-link
                            :href="\Illuminate\Support\Facades\Route::has('public.catalog.index') ? route('public.catalog.index', ['lang' => app()->getLocale(), 'type' => $tile['entry']->id]) : null"
                            class="w-40 shrink-0 rounded-lg border border-gray-200 p-4 text-center sm:w-auto"
                        >
                            <span class="block font-medium text-ink">{{ $tile['entry']->name }}</span>
                            <span class="text-sm text-ink-muted">{{ $tile['count'] }}</span>
                        </x-public.nav-link>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Recommended objects --}}
        @if ($recommendedObjects->isNotEmpty())
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.home.recommended_heading') }}</h2>
                <div class="mt-4 flex gap-4 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 sm:overflow-visible lg:grid-cols-3">
                    @foreach ($recommendedObjects as $object)
                        <div class="w-80 shrink-0 sm:w-auto">
                            <x-object-card :object="$object" wire:key="home-recommended-{{ $object->id }}" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Banner slot (mid) --}}
        @if ($bannerMid)
            <section class="mb-10">
                <a href="{{ route('banners.click', ['banner' => $bannerMid->id]) }}">
                    <img src="{{ $bannerMid->getFirstMediaUrl('desktop_creative') }}" alt="{{ $bannerMid->link_text }}" class="w-full rounded-lg">
                </a>
            </section>
        @endif

        {{-- Promotions --}}
        @if ($promotions->isNotEmpty())
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.territory.promotions') }}</h2>
                <div class="mt-4 flex gap-4 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 sm:overflow-visible lg:grid-cols-4">
                    @foreach ($promotions as $promotion)
                        <x-public.nav-link
                            :href="\Illuminate\Support\Facades\Route::has('public.promotions.show') ? route('public.promotions.show', ['lang' => app()->getLocale(), 'promotion' => $promotion->id]) : null"
                            class="block w-64 shrink-0 rounded-lg border border-gray-200 p-4 sm:w-auto"
                        >
                            <p class="font-medium text-ink">{{ $promotion->title }}</p>
                        </x-public.nav-link>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Newly added objects --}}
        @if ($newestObjects->isNotEmpty())
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.home.newest_heading') }}</h2>
                <div class="mt-4 flex gap-4 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 sm:overflow-visible lg:grid-cols-3">
                    @foreach ($newestObjects as $object)
                        <div class="w-80 shrink-0 sm:w-auto">
                            <x-object-card :object="$object" wire:key="home-newest-{{ $object->id }}" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Popular cities --}}
        @if (count($popularCities) > 0)
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.home.cities_heading') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
                    @foreach ($popularCities as $city)
                        <a
                            href="{{ $city->url }}"
                            class="rounded-lg border border-gray-200 p-3 text-center text-sm hover:border-brand"
                        >
                            {{ $city->name }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Map --}}
        @if ($country)
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.territory.map') }}</h2>
                <div class="mt-4">
                    <x-public.map :country-id="$country->id" :zoom="6" />
                </div>
            </section>
        @endif

        {{-- News + articles --}}
        @if ($newsItems->isNotEmpty() || $articles->isNotEmpty())
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.home.editorial_heading') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($newsItems as $newsItem)
                        <x-public.nav-link
                            :href="\Illuminate\Support\Facades\Route::has('public.news.show') ? route('public.news.show', ['lang' => app()->getLocale(), 'newsItem' => $newsItem->id]) : null"
                            class="block rounded-lg border border-gray-200 p-4"
                        >
                            <span class="text-xs font-semibold uppercase text-brand">{{ __('public.shell.nav.news') }}</span>
                            <p class="mt-1 font-medium text-ink">{{ $newsItem->title }}</p>
                        </x-public.nav-link>
                    @endforeach
                    @foreach ($articles as $article)
                        <x-public.nav-link
                            :href="\Illuminate\Support\Facades\Route::has('public.blog.show') ? route('public.blog.show', ['lang' => app()->getLocale(), 'article' => $article->id]) : null"
                            class="block rounded-lg border border-gray-200 p-4"
                        >
                            <span class="text-xs font-semibold uppercase text-brand">{{ __('public.shell.nav.blog') }}</span>
                            <p class="mt-1 font-medium text-ink">{{ $article->title }}</p>
                        </x-public.nav-link>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Reviews --}}
        @if ($reviews->isNotEmpty())
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.home.reviews_heading') }}</h2>
                <div class="mt-4 flex gap-4 overflow-x-auto pb-2 sm:grid sm:grid-cols-2 sm:overflow-visible lg:grid-cols-3">
                    @foreach ($reviews as $review)
                        <div class="w-72 shrink-0 rounded-lg border border-gray-200 p-4 sm:w-auto">
                            <p class="text-sm font-semibold text-ink">{{ $review->object->name }}</p>
                            <p class="mt-1 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit($review->body, 140) }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Informational block — translated editorial copy, always present --}}
        <section class="mb-10 max-w-3xl">
            <h2 class="text-xl font-semibold text-ink">{{ __('public.home.informational.title') }}</h2>
            <p class="mt-3 text-ink">{{ __('public.home.informational.body') }}</p>
        </section>

        {{-- Partners --}}
        @if ($partners->isNotEmpty())
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.home.partners_heading') }}</h2>
                <div class="mt-4 flex flex-wrap items-center gap-6">
                    @foreach ($partners as $partner)
                        <a href="{{ route('banners.click', ['banner' => $partner->id]) }}" class="block h-12">
                            <img src="{{ $partner->getFirstMediaUrl('desktop_creative') }}" alt="{{ $partner->link_text }}" class="h-full w-auto object-contain">
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Banner slot (bottom) --}}
        @if ($bannerBottom)
            <section>
                <a href="{{ route('banners.click', ['banner' => $bannerBottom->id]) }}">
                    <img src="{{ $bannerBottom->getFirstMediaUrl('desktop_creative') }}" alt="{{ $bannerBottom->link_text }}" class="w-full rounded-lg">
                </a>
            </section>
        @endif
    </div>
</x-layouts.public>
