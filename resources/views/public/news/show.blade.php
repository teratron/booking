@php
    $urls = app(\App\Services\Seo\PublicUrlGenerator::class);
@endphp
<x-layouts.public :metadata="$metadata" :breadcrumbs="$breadcrumbs" :structured-data="$structuredData">
    <div class="mx-auto max-w-3xl px-4 py-8 lg:px-8">
        @if ($newsItem->is_pinned)
            <span class="text-xs font-semibold uppercase text-brand">{{ __('public.news.pinned') }}</span>
        @endif

        <h1 class="mt-2 text-3xl font-medium text-ink">{{ $newsItem->title }}</h1>

        @if ($newsItem->publish_at)
            <p class="mt-2 text-sm text-ink-muted">{{ $newsItem->publish_at->toFormattedDateString() }}</p>
        @endif

        @if ($newsItem->getFirstMediaUrl('cover_image'))
            <img
                src="{{ $newsItem->getFirstMediaUrl('cover_image') }}"
                alt="{{ $newsItem->title }}"
                class="mt-6 w-full rounded-lg object-cover"
            >
        @endif

        <div class="mt-6 text-ink">
            {{ $newsItem->body }}
        </div>

        @if ($newsItem->territory || $newsItem->object)
            <div class="mt-8 flex flex-wrap gap-3">
                @if ($newsItem->territory && $urls->territoryUrl($newsItem->territory))
                    <a
                        href="{{ $urls->territoryUrl($newsItem->territory) }}"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm text-ink hover:border-brand hover:text-brand"
                    >
                        {{ $newsItem->territory->name }}
                    </a>
                @endif
                @if ($newsItem->object && $urls->objectUrl($newsItem->object))
                    <a
                        href="{{ $urls->objectUrl($newsItem->object) }}"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-ink hover:border-brand"
                    >
                        {{ $newsItem->object->name }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</x-layouts.public>
