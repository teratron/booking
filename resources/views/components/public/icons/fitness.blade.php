{{--
    Sport & fitness section glyph — Figma's hotel page (node 244:144 →
    85:555, layer "ion:fitness") pastes in an Iconify instance rather than
    a traced vector; the MCP asset-download call needed for its exact path
    was already over the session's Starter-plan quota (see the filter
    icon's own comment). Built as a plain dumbbell silhouette — the shape
    that glyph, and every "fitness"/"gym" icon in general use, draws.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <g fill="currentColor">
        <rect x="2" y="10" width="2" height="4" />
        <rect x="5" y="8" width="2" height="8" />
        <rect x="9" y="11" width="6" height="2" />
        <rect x="17" y="8" width="2" height="8" />
        <rect x="20" y="10" width="2" height="4" />
    </g>
</svg>
