{{--
    Breakfast amenity glyph — Figma's room details and hotel page (nodes
    85:223/235/247 and 85:216 plus the home/catalog cards, layers
    "ic:baseline-free-breakfast"/"ic:outline-free-breakfast" — the same
    concept in two icon-library styles) paste in Iconify instances rather
    than a traced vector; the MCP asset-download call needed for exact path
    data was already over the session's Starter-plan quota (see the filter
    icon's own comment). Built as the standard coffee-cup-with-steam
    pictogram every "breakfast" icon in general use shares. The cup and its
    handle use sharp corners rather than the source glyph's rounded ones:
    the project's one available render check (ImageMagick's minimal SVG
    coder) silently drops any path using the SVG arc command, unlike a real
    browser — the steam marks below keep their curves since those use the
    bezier commands that render fine either way.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <path fill="currentColor" d="M4 8h12l-1 8H5z" />
    <path fill="currentColor" d="M16 9h2v1h1v3h-1v1h-2v-1h1v-3h-1z" />
    <rect fill="currentColor" x="3" y="18" width="14" height="2" rx="1" />
    <path fill="currentColor" d="M8 1.5c-.4.6-.4 1.2 0 1.8s.4 1.2 0 1.8h1.4c.3-.6.3-1.2 0-1.8s-.3-1.2 0-1.8H8zM11.5 1.5c-.4.6-.4 1.2 0 1.8s.4 1.2 0 1.8h1.4c.3-.6.3-1.2 0-1.8s-.3-1.2 0-1.8h-1.4z" />
</svg>
