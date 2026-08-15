<x-layouts.public :title="$newsItem->title" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-3xl px-4 py-8 lg:px-8">
        @if ($newsItem->is_pinned)
            <span class="text-xs font-semibold uppercase text-brand">{{ __('public.news.pinned') }}</span>
        @endif

        <h1 class="mt-2 text-3xl font-semibold text-ink">{{ $newsItem->title }}</h1>

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
                @if ($newsItem->territory)
                    <a
                        href="{{ route('public.territories.show', ['lang' => app()->getLocale(), 'territory' => $newsItem->territory]) }}"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm text-ink hover:border-brand hover:text-brand"
                    >
                        {{ $newsItem->territory->name }}
                    </a>
                @endif
                @if ($newsItem->object)
                    <a
                        href="{{ route('public.objects.show', ['lang' => app()->getLocale(), 'object' => $newsItem->object]) }}"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-ink hover:border-brand"
                    >
                        {{ $newsItem->object->name }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</x-layouts.public>
