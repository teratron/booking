@php
    $urls = app(\App\Services\Seo\PublicUrlGenerator::class);
@endphp
<x-layouts.public :metadata="$metadata" :breadcrumbs="$breadcrumbs">
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

        @foreach ($catalogBlocks as $block)
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ $block['type']->name }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($block['objects'] as $object)
                        <x-object-card :object="$object" wire:key="territory-card-{{ $object->id }}" />
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($newsItems->isNotEmpty())
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.shell.nav.news') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($newsItems as $newsItem)
                        <x-public.nav-link
                            :href="\Illuminate\Support\Facades\Route::has('public.news.show') ? route('public.news.show', ['lang' => app()->getLocale(), 'newsItem' => $newsItem->id]) : null"
                            class="block rounded-lg border border-gray-200 p-4 hover:border-brand"
                        >
                            <p class="font-medium text-ink">{{ $newsItem->title }}</p>
                            @if ($newsItem->summary)
                                <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ $newsItem->summary }}</p>
                            @endif
                        </x-public.nav-link>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($promotions->isNotEmpty())
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.territory.promotions') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($promotions as $promotion)
                        <x-public.nav-link
                            :href="\Illuminate\Support\Facades\Route::has('public.promotions.show') ? route('public.promotions.show', ['lang' => app()->getLocale(), 'promotion' => $promotion->id]) : null"
                            class="block rounded-lg border border-gray-200 p-4 hover:border-brand"
                        >
                            <p class="font-medium text-ink">{{ $promotion->title }}</p>
                            @if ($promotion->summary)
                                <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ $promotion->summary }}</p>
                            @endif
                        </x-public.nav-link>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($territory->latitude !== null && $territory->longitude !== null)
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.territory.map') }}</h2>
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
                <h2 class="text-xl font-semibold text-ink">{{ __('public.territory.explore') }}</h2>
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
