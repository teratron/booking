{{--
    Telegram mark — vector, not the raster badge Figma's own footer node
    (225:4047) pastes in as a raw PNG fill; see the instagram icon
    component's own comment for why. The paper-plane glyph is the same
    silhouette Telegram's own mark and the ubiquitous "send" icon share —
    a plain outline built from straight lines only, not a bezier-traced
    illustration.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 28 28', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <rect width="28" height="28" rx="6" fill="#29A9EB" />
    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" fill="white" transform="translate(2,2) scale(0.9)" />
</svg>
