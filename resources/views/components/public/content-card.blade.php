{{--
    The one card shape news items, promotions, and blog articles all
    share — they differ in fields, not in how a card looks. `:href` may
    point at a route not registered yet; <x-public.nav-link> renders it
    inert rather than dead-linking until that page exists.

    `cover-image-url` was left unwired here even after all three content
    models (Article, NewsItem, Promotion) grew a `cover_image`/`image`
    media collection and a computed `coverImageUrl` on their own
    `ContentSummary` DTO (App\Support\Content) — the card kept rendering
    text-only. Figma's own blog/news frames show a photo on every card;
    this closes that gap without requiring callers to switch to the DTO.
--}}
@props(['title', 'summary' => null, 'href' => null, 'kicker' => null, 'coverImageUrl' => null])
<x-public.nav-link :href="$href" class="block overflow-hidden rounded-lg border border-gray-200 hover:border-brand">
    @if ($coverImageUrl)
        <img src="{{ $coverImageUrl }}" alt="" class="h-40 w-full object-cover" loading="lazy">
    @endif
    <div class="p-4">
        @if ($kicker)
            <span class="text-xs font-semibold uppercase text-brand">{{ $kicker }}</span>
        @endif
        <p class="font-medium text-ink">{{ $title }}</p>
        @if ($summary)
            <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ $summary }}</p>
        @endif
        {{ $slot ?? '' }}
    </div>
</x-public.nav-link>
