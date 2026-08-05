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
        '/\bT-\d{3,5}\b/',
        '/\bphase-\d+\b/i',
        '/\b(?:PLAN|TASKS|INDEX|RULES)\.md\b/',
        '/\bl[12]-[a-z][a-z-]*\.md\b/',
    ];

    $offenders = [];

    $files = (new Finder)
        ->files()
        ->in([
            $root.'/app',
            $root.'/resources',
            $root.'/database',
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
