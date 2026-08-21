<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Pipeline Containment & Gate Parity
|--------------------------------------------------------------------------
|
| The mechanical form of a rule the project already enforces for product
| code (see ContainmentTest.php), extended to the delivery pipeline itself:
| delete the design and planning directories and every workflow, script, and
| documented procedure must still run unchanged. A content-scanning test,
| not the arch() DSL — this is a text-pattern question, not a dependency-
| graph one.
|
| Architecture tests run outside the Laravel boot cycle (see tests/Pest.php),
| so base_path() is unavailable — the project root is derived from this
| file's own location, matching ContainmentTest.php's own convention.
|
*/

test('no specification or design-tooling references leak into the delivery pipeline', function (): void {
    $root = dirname(__DIR__, 2);

    $forbidden = [
        '/\.design\//',
        '/\.magic\//',
        '/\bT-\d{3,5}\b/',
        '/\bphase-\d+\b/i',
        '/\bPhase\s+\d+\b/',
        '/\b(?:PLAN|TASKS|INDEX|RULES)\.md\b/',
        '/\bl[12]-[a-z][a-z-]*\.md\b/',
    ];

    $offenders = [];

    $scan = function (string $relativePath, string $contents) use ($forbidden, &$offenders): void {
        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $offenders[] = $relativePath;

                return;
            }
        }
    };

    $files = (new Finder)
        ->files()
        ->in([
            $root.'/.github/workflows',
            $root.'/docs',
            $root.'/docker/deploy',
        ])
        ->name(['*.yml', '*.yaml', '*.md', '*.sh']);

    foreach ($files as $file) {
        $scan($file->getRelativePathname(), $file->getContents());
    }

    // composer.json sits at the repository root, outside every directory
    // the Finder above scans — checked explicitly rather than widening it
    // to the whole root, which would re-scan vendor/ and node_modules/.
    $scan('composer.json', (string) file_get_contents($root.'/composer.json'));

    expect($offenders)->toBe([]);
});

test('the release path gates on the same composer quality script a developer runs', function (): void {
    $root = dirname(__DIR__, 2);
    $qualityWorkflow = (string) file_get_contents($root.'/.github/workflows/quality.yml');

    // Not "a step that happens to also run lint/analyse/test separately" —
    // the literal command, so a developer's own `composer quality` and the
    // pipeline's own gate can never silently diverge into two different
    // checklists that happen to share a name.
    expect($qualityWorkflow)->toContain('composer quality');

    $composerScripts = json_decode((string) file_get_contents($root.'/composer.json'), true)['scripts'] ?? [];

    expect($composerScripts)->toHaveKey('quality');
});
