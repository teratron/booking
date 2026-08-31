<x-layouts.public :title="__('public.shell.nav.blog')" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <h1 class="text-3xl font-medium text-ink">{{ __('public.shell.nav.blog') }}</h1>

        @if ($articles->isEmpty())
            <p class="mt-6 text-ink-muted">{{ __('public.blog.empty') }}</p>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-public.content-card
                        :title="$article->title"
                        :summary="$article->summary"
                        :href="route('public.blog.show', ['lang' => app()->getLocale(), 'slug' => $article->slug])"
                        :kicker="$article->category?->name"
                        :cover-image-url="$article->getFirstMediaUrl('cover_image') ?: null"
                    >
                        @if ($article->tags->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($article->tags as $tag)
                                    <span class="rounded-full bg-surface-muted px-2 py-0.5 text-xs text-ink-muted">{{ $tag->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </x-public.content-card>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $articles->links() }}
            </div>
        @endif
    </div>
</x-layouts.public>
