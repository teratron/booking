{{--
    Close/reset glyph — Figma's mobile filter drawer (node 227:5120, layer
    "ep:circle-close") pastes in an Iconify instance rather than a traced
    vector; the MCP asset-download call needed for its exact path was
    already over the session's Starter-plan quota (see the filter icon's
    own comment). Built as a plain bold X — the ring in the source glyph's
    name is dropped rather than approximated with the SVG arc command,
    since the project's one available render check (ImageMagick's minimal
    SVG coder) silently drops any path using arcs, unlike a real browser;
    every icon in this directory sticks to line and curve commands it
    actually renders. An X alone is still an unambiguous "close" glyph on
    its own (Heroicons and Feather both ship exactly this).
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <path fill="currentColor"
        d="M18.3 5.71 12 12.01 5.7 5.71 4.29 7.12 10.59 13.42 4.29 19.72 5.7 21.13 12 14.83 18.3 21.13 19.71 19.72 13.41 13.42 19.71 7.12z" />
</svg>
