{{--
    Instagram mark — vector, not the raster badge Figma's own footer node
    (225:4047) pastes in as a raw PNG fill; that file has no vector layer
    for any of the three social glyphs (Figma is a visual concept, not the
    asset source), and every other icon on the public site is already
    inline SVG. Traced from the mark's own geometry (rounded-square frame,
    circular lens, flash dot) using only filled shapes, layering the brand
    color over white to read as a ring — not a stroked outline, which
    several SVG renderers used in this project's own tooling draw
    inconsistently.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 28 28', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <rect width="28" height="28" rx="6" fill="#E4405F" />
    <rect x="7" y="7" width="14" height="14" rx="4" fill="white" />
    <rect x="8.6" y="8.6" width="10.8" height="10.8" rx="3" fill="#E4405F" />
    <circle cx="14" cy="14" r="3.4" fill="white" />
    <circle cx="14" cy="14" r="2.1" fill="#E4405F" />
    <circle cx="18" cy="10" r="1" fill="white" />
</svg>
