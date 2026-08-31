{{--
    Carousel navigation arrow — Figma's home/catalog/hotel-page card
    carousels (nodes 225:3652/3653, 225:2037/2038, 85:1368/1369, layers
    "Arrow 3"/"Arrow 4" flanking the pagination dots) draw a pair of
    mirror-image arrows rather than one reusable glyph; the MCP
    asset-download call needed for their exact path data was already over
    the session's Starter-plan quota (see the filter icon's own comment).
    One right-pointing chevron covers both: rotate 180deg (e.g. Tailwind's
    `rotate-180`) at the call site for the "previous" direction instead of
    shipping a second, mirrored file.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <path fill="currentColor" d="M9.4 6L8 7.4l4.6 4.6L8 16.6 9.4 18l6-6z" />
</svg>
