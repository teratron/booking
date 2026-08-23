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
    'territoryId' => null,
    'countryId' => null,
    'centerLat' => 45.0,
    'centerLng' => 30.0,
    'zoom' => 5,
])
@php
    $lang = app()->getLocale();
    $tileConfig = app(\App\Services\Integrations\MapTileConfigResolver::class);
@endphp
@if (! $tileConfig->hasKey())
    {{-- No provider key configured — a labelled placeholder, never a
         silent empty box or a tile request that can only ever 403. --}}
    <div
        class="flex h-120 w-full flex-col items-center justify-center gap-2 rounded-lg bg-gray-100 text-center text-sm text-ink-muted dark:bg-gray-800"
        role="img"
        aria-label="{{ __('public.shell.map.unavailable') }}"
    >
        <span>{{ __('public.shell.map.unavailable') }}</span>
    </div>
@else
    @php
        $config = [
            'styleUrl' => $tileConfig->styleUrl(),
            'centerLat' => $centerLat,
            'centerLng' => $centerLng,
            'zoom' => $zoom,
            'brandColour' => '#f8bb44',
            'pinsUrl' => route('public.map.pins.index', ['lang' => $lang]),
            'pinCardUrlTemplate' => route('public.map.pins.show', ['lang' => $lang, 'object' => '__OBJECT__']),
            'initialFilters' => array_filter(['type' => $objectTypeId, 'territory_id' => $territoryId, 'country_id' => $countryId]),
        ];
    @endphp
    @vite(['resources/js/map.js'])
    <div
        x-data="catalogMap(@js($config))"
        class="h-120 w-full overflow-hidden rounded-lg"
        role="application"
        aria-label="{{ __('public.shell.map.label') }}"
    >
        <div x-ref="container" class="h-full w-full"></div>
    </div>
@endif
