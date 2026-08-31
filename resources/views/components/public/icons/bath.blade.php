{{--
    Bathroom amenity glyph — Figma's room details ("удобства"/"основная
    инфа" blocks, nodes 85:219/231/243, layer "majesticons:bath-shower",
    plus an unnamed "Vector" at 85:643 used for the same "Душ" concept
    elsewhere on the same page) pastes in Iconify/hand-drawn instances
    rather than a traced vector; the MCP asset-download call needed for
    exact path data was already over the session's Starter-plan quota (see
    the filter icon's own comment). Both source layers draw the same
    concept — a shower — so one component covers both call sites. Built
    from a rect (pipe), an ellipse (shower head) and three rects
    (droplets): the project's one available render check (ImageMagick's
    minimal SVG coder) silently drops any path using the SVG arc command,
    unlike a real browser, so this sticks to native basic shapes instead
    of a hand-rolled curved path.
--}}
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>
    <rect fill="currentColor" x="10" y="1" width="4" height="6" rx="1" />
    <ellipse fill="currentColor" cx="12" cy="9.5" rx="6" ry="3" />
    <g fill="currentColor">
        <rect x="7" y="15" width="2" height="3" rx="1" />
        <rect x="11" y="17" width="2" height="3" rx="1" />
        <rect x="15" y="15" width="2" height="3" rx="1" />
    </g>
</svg>
