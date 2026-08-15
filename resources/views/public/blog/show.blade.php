@php
    $urls = app(\App\Services\Seo\PublicUrlGenerator::class);
@endphp
<x-layouts.public :title="$article->title" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-3xl px-4 py-8 lg:px-8">
        @if ($article->category)
            <span class="text-xs font-semibold uppercase text-brand">{{ $article->category->name }}</span>
        @endif

        <h1 class="mt-2 text-3xl font-semibold text-ink">{{ $article->title }}</h1>

        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-ink-muted">
            @if ($article->author)
                <span>{{ __('public.blog.by', ['name' => $article->author->name]) }}</span>
            @endif
            @if ($article->publish_at)
                <span>{{ $article->publish_at->toFormattedDateString() }}</span>
            @endif
        </div>

        @if ($article->getFirstMediaUrl('cover_image'))
            <img
                src="{{ $article->getFirstMediaUrl('cover_image') }}"
                alt="{{ $article->title }}"
                class="mt-6 w-full rounded-lg object-cover"
            >
        @endif

        <div class="mt-6 text-ink">
            {{ $article->body }}
        </div>

        {{-- Tags --}}
        @if ($article->tags->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-sm font-medium text-ink-muted">{{ __('public.blog.tags_heading') }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($article->tags as $tag)
                        <span class="rounded-full bg-surface-muted px-3 py-1 text-sm text-ink">{{ $tag->name }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Related objects --}}
        @if ($article->objects->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.blog.related_objects_heading') }}</h2>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($article->objects as $relatedObject)
                        <a
                            href="{{ $urls->objectUrl($relatedObject) }}"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-ink hover:border-brand"
                        >
                            {{ $relatedObject->name }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Related territories --}}
        @if ($article->territories->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-xl font-semibold text-ink">{{ __('public.blog.related_territories_heading') }}</h2>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($article->territories as $relatedTerritory)
                        <a
                            href="{{ $urls->territoryUrl($relatedTerritory) }}"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm text-ink hover:border-brand hover:text-brand"
                        >
                            {{ $relatedTerritory->name }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
