{{--
    The layout every public route renders inside. A page passes either
    `:metadata` (a resolved App\Support\Seo\ResolvedMetadata — title, meta
    description, canonical, indexability, and Open Graph fields, all
    already guaranteed non-empty by MetadataResolver) or a bare `:title`
    for the handful of static pages this task does not cover. Once a page
    is below the home page it also passes `:breadcrumbs` (a list of
    ['label' => ..., 'url' => ...]). `:noindex` emits a robots directive
    without needing a full metadata object, for pages that must never be
    indexed, such as the 404 page.
--}}
@props(['title' => null, 'metadata' => null, 'breadcrumbs' => [], 'noindex' => false])
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
