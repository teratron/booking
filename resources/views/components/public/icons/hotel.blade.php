{{--
    Hotel-type badge — Figma's catalog card (node 225:2188, layer
    "ri:hotel-fill") pastes in an Iconify instance next to the "Отели"
    label rather than a traced vector; the MCP asset-download call needed
    to pull its exact path was already over the session's Starter-plan
    quota (see the filter icon's own comment). Built as a plain building
    silhouette instead — deliberately distinct from the room "bed" amenity
    icon below, since the two read as different concepts (object type vs.
    room furnishing) even though both source glyphs are bed-shaped in the
    original icon library.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <path fill="currentColor" d="M4 21V10l8-6 8 6v11h-5v-6H9v6H4z" />
</svg>
