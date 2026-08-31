{{--
    Photo/video upload glyph — Figma's owner-facing "add hotel" form (node
    235:6054, layer "ic:baseline-photo") pastes in an Iconify instance
    rather than a traced vector; the MCP asset-download call needed for its
    exact path was already over the session's Starter-plan quota (see the
    filter icon's own comment). Built as the standard "photo" pictogram
    every icon set in general use shares (frame, mountain silhouette, sun
    dot) — the same button also labels a "Выбрать видео" (choose video)
    action in the design, and this glyph reads for either without a second,
    near-identical icon. The frame uses sharp corners rather than the
    source glyph's rounded ones: the project's one available render check
    (ImageMagick's minimal SVG coder) silently drops any path using the
    SVG arc command, unlike a real browser, so every icon in this
    directory sticks to shapes it actually renders.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M2 5h20v14H2V5zm2 2v10h16V7H4z" />
    <circle fill="currentColor" cx="8" cy="10.5" r="1.5" />
    <path fill="currentColor" d="M6 16l3.5-4.5 2.5 3 3.5-4.5L20 16H6z" />
</svg>
