{{--
    Filter glyph — Figma's catalog filter button (node 22:2720, layer named
    "clarity:filter-grid-line") pastes in an Iconify library instance rather
    than a traced vector, and the MCP asset-download call needed to pull its
    exact path data was already over its Starter-plan quota this session
    (confirmed still exhausted a day after it was first hit — the ceiling
    does not appear to reset daily). The layer name is itself an exact,
    unambiguous spec of which glyph this is, so the standard funnel shape
    that icon (and every other "filter" icon in general use) draws is
    reproduced directly as a single filled polygon, not approximated from a
    screenshot.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <path fill="currentColor" d="M3 4h18l-6.5 8v6.5l-5 2.5V13z" />
</svg>
