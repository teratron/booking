{{--
    Bed amenity glyph — Figma's room details ("удобства"/"основная инфа"
    blocks, nodes 85:217/229/241, layer "ion:bed", plus an unnamed "Vector"
    at 85:569 used for the same "кровать" concept elsewhere on the same
    page) pastes in Iconify/hand-drawn instances rather than a traced
    vector; the MCP asset-download call needed for exact path data was
    already over the session's Starter-plan quota (see the filter icon's
    own comment). Both source layers draw the same concept — a bed — so
    one component covers both call sites rather than two near-identical
    icons. Built from plain rectangles only (headboard, pillow, mattress,
    legs): the project's one available render check (ImageMagick's minimal
    SVG coder) silently drops any path using the SVG arc command, unlike a
    real browser, so this sticks to `rect`, which renders correctly either
    way.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <g fill="currentColor">
        <rect x="2" y="7" width="3" height="9" rx="1" />
        <rect x="6" y="9" width="5" height="3" rx="1" />
        <rect x="2" y="12" width="20" height="4" />
        <rect x="3" y="16" width="2" height="3" />
        <rect x="19" y="16" width="2" height="3" />
    </g>
</svg>
