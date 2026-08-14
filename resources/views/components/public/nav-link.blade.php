{{--
    A link whose target route may not be registered yet — a later task
    adds it. Renders as a real link when the route exists, or an inert,
    visually distinct span otherwise, so the shell never dead-links.
--}}
@props(['href' => null])
@if ($href)
    <a href="{{ $href }}" {{ $attributes }}>{{ $slot }}</a>
@else
    <span {{ $attributes->merge(['class' => 'cursor-not-allowed opacity-60']) }}>{{ $slot }}</span>
@endif
