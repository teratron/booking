<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Specification Containment
|--------------------------------------------------------------------------
|
| Traceability runs one way: specification artefacts reference code, code
| never references specification artefacts. Releases may ship without the
| design directory, so a reference to it from product code becomes dead
| content. This is a content search, not a dependency-graph question, so it
| runs as a plain test rather than through the arch() DSL.
|
*/

test('no specification references leak into product code', function (): void {
    // Architecture tests run outside the Laravel boot cycle (see tests/Pest.php),
    // so base_path() is unavailable — the project root is derived from this
    // file's own location instead: tests/Architecture -> tests -> root.
    $root = dirname(__DIR__, 2);

    $forbidden = [
        '/\.design\//',
        // Task IDs are T-{phase}{track}{seq} — a phase number, a single
        // uppercase track letter, then a sequence number, optionally
        // suffixed with a sub-step. A fixed digit-only width never matches
        // this real shape, which always has a letter between two digit
        // runs; writing a live-matching example here would trip this very
        // test against its own source.
        '/\bT-\d+[A-Z]\d+(?:\.\d+)?\b/',
        '/\bphase-\d+\b/i',
        '/\bPhase\s+\d+\b/', // prose form ("Phase" + a number in running text) — the file-form pattern above only catches the hyphenated slug
        '/\b(?:PLAN|TASKS|INDEX|RULES)\.md\b/',
        '/\bl[12]-[a-z][a-z-]*\.md\b/',
        // A bare section-symbol reference is only a leak when it points at
        // this project's own internal specification set — a citation of the
        // client's own original technical specification (marked "[TZ]"
        // throughout this codebase, e.g. "[TZ]` §17/§100") is a permanent,
        // legitimate reference to an external document that predates and
        // outlives the design scaffolding, not part of it. The SKIP/FAIL
        // branch consumes every "[TZ] ... §N(.N)? ..." citation (including a
        // sentence naming several, joined by "and" or "/") up to the next
        // full stop before the unqualified-§ branch is tried, so a real TZ
        // citation never counts as a leak here — and this comment names no
        // unqualified §-number itself, for the same reason the pattern
        // above this one avoids a live task-ID example.
        '/\[TZ\][^.]*?§\d+(?:\.\d+)?(?:[^.]*?§\d+(?:\.\d+)?)*(*SKIP)(*FAIL)|§\d/',
    ];

    $offenders = [];

    $files = (new Finder)
        ->files()
        ->in([
            $root.'/app',
            $root.'/resources',
            $root.'/database',
            $root.'/tests',
        ])
        ->name(['*.php', '*.blade.php']);

    foreach ($files as $file) {
        $contents = $file->getContents();

        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $offenders[] = $file->getRelativePathname();

                break;
            }
        }
    }

    expect($offenders)->toBe([]);
});
