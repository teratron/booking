{{--
    A banner's creative, served by viewport rather than by user-agent
    sniffing (`[TZ]` §24.2): the mobile creative renders under a `max-width`
    media query when an advertiser uploaded one, falling back to the
    desktop creative at every width otherwise — a banner with only a
    desktop creative is unaffected.
--}}
@props(['banner'])
<picture>
    @if ($banner->getFirstMediaUrl('mobile_creative'))
        <source media="(max-width: 639px)" srcset="{{ $banner->getFirstMediaUrl('mobile_creative') }}">
    @endif
    <img src="{{ $banner->getFirstMediaUrl('desktop_creative') }}" alt="{{ $banner->link_text }}" {{ $attributes }}>
</picture>
