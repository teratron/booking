{{--
    Pool & entertainment section glyph — Figma's hotel page (node 244:138
    → 85:550, layer "carbon:join-full") pastes in an Iconify instance
    rather than a traced vector; the MCP asset-download call needed for its
    exact path was already over the session's Starter-plan quota (see the
    filter icon's own comment). "Join full" is a Venn-diagram glyph
    (two circles, their union shaded) reused here as the section marker for
    "Бассейн / Аквапарк / Массаж / Дискотека" — reproduced as two
    overlapping filled circles, the second at reduced opacity so the
    overlap reads as the shaded union rather than one flat blob.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <circle fill="currentColor" cx="9" cy="12" r="7" />
    <circle fill="currentColor" fill-opacity="0.55" cx="15" cy="12" r="7" />
</svg>
