<x-layouts.public :title="__('public.shell.nav.news')" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <h1 class="text-3xl font-medium text-ink">{{ __('public.shell.nav.news') }}</h1>

        @if ($newsItems->isEmpty())
            <p class="mt-6 text-ink-muted">{{ __('public.news.empty') }}</p>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($newsItems as $newsItem)
                    <x-public.content-card
                        :title="$newsItem->title"
                        :summary="$newsItem->summary"
                        :href="route('public.news.show', ['lang' => app()->getLocale(), 'slug' => $newsItem->slug])"
                        :kicker="$newsItem->is_pinned ? __('public.news.pinned') : null"
                        :cover-image-url="$newsItem->getFirstMediaUrl('cover_image') ?: null"
                    />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $newsItems->links() }}
            </div>
        @endif
    </div>
</x-layouts.public>
