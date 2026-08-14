{{--
    The layout every public route renders inside. A page passes `:title`
    and, once it is below the home page, `:breadcrumbs` (a list of
    ['label' => ..., 'url' => ...]). `:noindex` emits a robots directive for
    pages that must never be indexed, such as the 404 page.
--}}
@props(['title' => null, 'breadcrumbs' => [], 'noindex' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? app(\App\Services\Settings\SettingsRepository::class)->get('portal.name') }}</title>

    @if ($noindex)
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
