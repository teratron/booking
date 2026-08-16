{{--
    The layout every public route renders inside. A page passes either
    `:metadata` (a resolved App\Support\Seo\ResolvedMetadata — title, meta
    description, canonical, indexability, and Open Graph fields, all
    already guaranteed non-empty by MetadataResolver) or a bare `:title`
    for the handful of static pages this task does not cover. Once a page
    is below the home page it also passes `:breadcrumbs` (a list of
    ['label' => ..., 'url' => ...]) — used both for the visible breadcrumb
    trail and, automatically, for a BreadcrumbList structured-data block, so
    no page needs to build that block itself. `:structuredData` carries any
    further Schema.org blocks (StructuredDataBuilder's own output) specific
    to that page's entity. `:noindex` emits a robots directive without
    needing a full metadata object, for pages that must never be indexed,
    such as the 404 page.
--}}
@props(['title' => null, 'metadata' => null, 'breadcrumbs' => [], 'noindex' => false, 'structuredData' => []])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $metadata->title ?? $title ?? app(\App\Services\Settings\SettingsRepository::class)->get('portal.name') }}</title>

    @if ($metadata)
        <meta name="description" content="{{ $metadata->description }}">

        @if ($metadata->canonicalUrl)
            <link rel="canonical" href="{{ $metadata->canonicalUrl }}">
        @endif

        @if ($noindex || ! $metadata->indexable)
            <meta name="robots" content="noindex">
        @endif

        <meta property="og:title" content="{{ $metadata->ogTitle }}">
        <meta property="og:description" content="{{ $metadata->ogDescription }}">
        @if ($metadata->ogImageUrl)
            <meta property="og:image" content="{{ $metadata->ogImageUrl }}">
        @endif
    @elseif ($noindex)
        <meta name="robots" content="noindex">
    @endif

    @php
        $structuredDataBlocks = $structuredData;

        if (count($breadcrumbs) > 0) {
            $structuredDataBlocks[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($breadcrumbs)->values()->map(fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['label'],
                    'item' => $crumb['url'],
                ])->all(),
            ];
        }
    @endphp

    {{--
        Deliberately no JSON_UNESCAPED_SLASHES: an unescaped "/" lets any
        field value containing the literal text "</script>" (an object
        name, a promotion summary) close this tag early and inject
        arbitrary markup. The HEX_* flags additionally escape the other
        HTML-sensitive characters JSON allows unescaped by default. Every
        structured-data JSON-LD parser accepts the resulting \/ and \u00XX
        escapes without any loss of meaning.
    --}}
    @foreach ($structuredDataBlocks as $block)
        <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endforeach

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="flex min-h-screen flex-col bg-white text-ink">
    <x-public.header />

    @if (count($breadcrumbs) > 0)
        <x-public.breadcrumbs :items="$breadcrumbs" />
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-public.footer />

    <x-public.feedback-overlay />

    @livewireScripts
</body>

</html>
