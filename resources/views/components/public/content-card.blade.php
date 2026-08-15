{{--
    The one card shape news items, promotions, and (eventually) blog
    articles all share — they differ in fields, not in how a card looks.
    `:href` may point at a route not registered yet; <x-public.nav-link>
    renders it inert rather than dead-linking until that page exists.
--}}
@props(['title', 'summary' => null, 'href' => null, 'kicker' => null])
<x-public.nav-link :href="$href" class="block rounded-lg border border-gray-200 p-4 hover:border-brand">
    @if ($kicker)
        <span class="text-xs font-semibold uppercase text-brand">{{ $kicker }}</span>
    @endif
    <p class="font-medium text-ink">{{ $title }}</p>
    @if ($summary)
        <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ $summary }}</p>
    @endif
    {{ $slot ?? '' }}
</x-public.nav-link>
