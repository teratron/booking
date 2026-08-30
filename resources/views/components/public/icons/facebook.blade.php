{{--
    Facebook mark — vector, not the raster badge Figma's own footer node
    (225:4047) pastes in as a raw PNG fill; see the instagram icon
    component's own comment for why. The glyph is a plain orthogonal
    outline (a stem with a hooked top and a crossbar, both straight-line
    steps rather than curves) instead of a bezier trace of a specific
    brand-kit file — legible at the 28px badge size this row renders at,
    and consistent with this project's other icons, which are all built
    from simple shapes rather than imported artwork.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 28 28', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <rect width="28" height="28" rx="6" fill="#1877F2" />
    <path d="M9 6 V22 H12 V15 H16 V12 H12 V9 H15 V6 Z" fill="white" />
</svg>
