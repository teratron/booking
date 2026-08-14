{{--
    The clustered catalog map. Renders pins for the filtered result set
    only, clustered client-side in MapLibre, with a compact card opening on
    click. Filter changes are picked up by dispatching a
    `catalog-filters-changed` window event carrying the new query
    parameters — a caller embedding this alongside a result list dispatches
    the same event both update from.
--}}
@props([
    'objectTypeId' => null,
    'centerLat' => 45.0,
    'centerLng' => 30.0,
    'zoom' => 5,
])
@php
    $lang = app()->getLocale();
    $config = [
        'styleUrl' => app(\App\Services\Integrations\MapTileConfigResolver::class)->styleUrl(),
        'centerLat' => $centerLat,
        'centerLng' => $centerLng,
        'zoom' => $zoom,
        'brandColour' => '#f8bb44',
        'pinsUrl' => route('public.map.pins.index', ['lang' => $lang]),
        'pinCardUrlTemplate' => route('public.map.pins.show', ['lang' => $lang, 'object' => '__OBJECT__']),
        'initialFilters' => array_filter(['type' => $objectTypeId]),
    ];
@endphp
@vite(['resources/js/map.js'])
<div
    x-data="catalogMap(@js($config))"
    class="h-[480px] w-full overflow-hidden rounded-lg"
    role="application"
    aria-label="{{ __('public.shell.map.label') }}"
>
    <div x-ref="container" class="h-full w-full"></div>
</div>
