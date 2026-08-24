{{--
    Heading + content row shared by every Home page block that lists a
    handful of cards or tiles: a horizontal scroller on phone, a grid from
    `sm:` up (per the home page spec's own responsive matrix). `columns`
    picks a fixed, Tailwind-scannable class pair rather than building one
    from a variable — Tailwind only sees literal class strings in source,
    so an interpolated `grid-cols-{$n}` is silently dropped from the
    compiled stylesheet. Item width stays the caller's own concern (it
    varies with content — a destination tile is not an object card) so
    this component only owns the row's scroll/grid behaviour.
--}}
@props(['heading', 'columns' => '3', 'gap' => '4'])
@php
    $gridClass = match ((string) $columns) {
        '2' => 'sm:grid-cols-2',
        '3' => 'sm:grid-cols-2 lg:grid-cols-3',
        '4' => 'sm:grid-cols-2 lg:grid-cols-4',
        '6' => 'sm:grid-cols-3 lg:grid-cols-6',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
    $gapClass = (string) $gap === '3' ? 'gap-3' : 'gap-4';
@endphp
<section class="mb-10">
    <x-public.section-heading>{{ $heading }}</x-public.section-heading>
    <div {{ $attributes->merge(['class' => "mt-4 flex {$gapClass} overflow-x-auto pb-2 sm:grid sm:overflow-visible {$gridClass}"]) }}>
        {{ $slot }}
    </div>
</section>
