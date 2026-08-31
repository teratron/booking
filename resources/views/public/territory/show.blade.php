@php
    $urls = app(\App\Services\Seo\PublicUrlGenerator::class);
@endphp
<x-layouts.public :metadata="$metadata" :breadcrumbs="$breadcrumbs" :structured-data="$structuredData">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        @if ($territory->hero_image_path)
            <div class="h-64 w-full overflow-hidden rounded-lg">
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::url($territory->hero_image_path) }}"
                    alt="{{ $territory->name }}"
                    class="h-full w-full object-cover"
                >
            </div>
        @endif

        <h1 class="mt-4 text-3xl font-semibold text-ink">{{ $territory->name }}</h1>

        @if ($territory->short_description)
            <p class="mt-3 max-w-3xl text-ink">{{ $territory->short_description }}</p>
        @endif

        @if ($bannerTop)
            <div class="mt-6">
                <a href="{{ route('banners.click', ['banner' => $bannerTop->id]) }}">
                    <x-public.banner-creative :banner="$bannerTop" class="w-full rounded-lg" />
                </a>
            </div>
        @endif

        @foreach ($catalogBlocks as $block)
            <section class="mt-10">
                <x-public.section-heading>{{ $block['type']->name }}</x-public.section-heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($block['objects'] as $object)
                        <x-object-card :object="$object" variant="tile" wire:key="territory-card-{{ $object->id }}" />
                    @endforeach
                </div>
            </section>

            @if ($bannerMid && $loop->first && ! $loop->last)
                <div class="mt-10">
                    <a href="{{ route('banners.click', ['banner' => $bannerMid->id]) }}">
                        <x-public.banner-creative :banner="$bannerMid" class="w-full rounded-lg" />
                    </a>
                </div>
            @endif
        @endforeach

        @if ($bannerBottom)
            <div class="mt-10">
                <a href="{{ route('banners.click', ['banner' => $bannerBottom->id]) }}">
                    <x-public.banner-creative :banner="$bannerBottom" class="w-full rounded-lg" />
                </a>
            </div>
        @endif

        @if ($newsItems->isNotEmpty())
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.shell.nav.news') }}</x-public.section-heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($newsItems as $newsItem)
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

        @if ($promotions->isNotEmpty())
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.territory.promotions') }}</x-public.section-heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($promotions as $promotion)
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

        @if ($territory->latitude !== null && $territory->longitude !== null)
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.territory.map') }}</x-public.section-heading>
                <div class="mt-4">
                    <x-public.map
                        :territory-id="$territory->id"
                        :center-lat="(float) $territory->latitude"
                        :center-lng="(float) $territory->longitude"
                        :zoom="11"
                    />
                </div>
            </section>
        @endif

        @if ($territory->full_description)
            <section class="mt-10 max-w-3xl text-ink">
                {{ $territory->full_description }}
            </section>
        @endif

        @if ($childTerritories->isNotEmpty())
            <section class="mt-10">
                <x-public.section-heading>{{ __('public.territory.explore') }}</x-public.section-heading>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($childTerritories as $child)
                        <a
                            href="{{ $urls->territoryUrl($child) }}"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm text-ink hover:border-brand hover:text-brand"
                        >
                            {{ $child->name }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
