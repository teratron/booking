{{--
    Air conditioner amenity glyph — Figma's room details block ("удобства",
    node 85:644, unnamed layer "Vector" next to "Кондиционер") has no
    Iconify library name to key off of, and the MCP asset-download call
    that would have pulled its exact path was already over the session's
    Starter-plan quota (see the filter icon's own comment), so this is a
    plain original rendering of the concept rather than a reproduction of
    a named glyph: a wall-unit body with two vent slits and a status dot,
    all cut from the fill via evenodd (not stroked), plus three angled
    airflow bars below. Every cut and corner is built from straight lines
    only, no rounding: the project's one available render check
    (ImageMagick's minimal SVG coder) silently drops any path using the
    SVG arc command, unlike a real browser.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"
        d="M2 4h20v8H2V4zm3 3h10v1.4H5V7zm0 2.4h10v1.4H5V9.4zm11.9-1.9h1.8v1.8h-1.8z" />
    <g fill="currentColor">
        <rect x="5.3" y="13.5" width="1.3" height="5" rx="0.6" transform="rotate(18 6 16)" />
        <rect x="10.35" y="13.5" width="1.3" height="5" rx="0.6" />
        <rect x="15.4" y="13.5" width="1.3" height="5" rx="0.6" transform="rotate(-18 16 16)" />
    </g>
</svg>
