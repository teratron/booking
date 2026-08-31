{{--
    A banner's creative, served by viewport rather than by user-agent
    sniffing (`[TZ]` §24.2): the mobile creative renders under a `max-width`
    media query when an advertiser uploaded one, falling back to the
    desktop creative at every width otherwise — a banner with only a
    desktop creative is unaffected.

    Renders nothing when the banner carries no desktop creative — the
    selection service's own contract is "no element at all, never an empty
    frame", and an <img> with an empty src is a broken-image icon, not an
    empty frame.
--}}
@props(['banner'])
@php($desktop = $banner->getFirstMediaUrl('desktop_creative'))
@if ($desktop)
    <picture>
        @if ($banner->getFirstMediaUrl('mobile_creative'))
            <source media="(max-width: 639px)" srcset="{{ $banner->getFirstMediaUrl('mobile_creative') }}">
        @endif
        <img src="{{ $desktop }}" alt="{{ $banner->link_text }}" {{ $attributes }}>
    </picture>
@endif
