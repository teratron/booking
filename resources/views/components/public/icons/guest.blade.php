{{--
    Guest/occupancy glyph — Figma's room details block (nodes 85:645 and
    85:647, layer "mdi:human-male", drawn twice side by side for "Два
    спальных места") pastes in an Iconify instance rather than a traced
    vector; the MCP asset-download call needed for its exact path was
    already over the session's Starter-plan quota (see the filter icon's
    own comment). Built as a plain person silhouette — the shape that
    glyph, and every "person"/"guest" icon in general use, draws. The
    design repeats this glyph per occupant; render one `<x-public.icons.guest>`
    per guest at the call site rather than baking a fixed count in here.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <circle fill="currentColor" cx="12" cy="4.2" r="2.2" />
    <path fill="currentColor" d="M8 9.5C8 8.1 9.8 7 12 7s4 1.1 4 2.5V14h-1.5v6h-2v-5h-1v5h-2v-6H8V9.5z" />
</svg>
