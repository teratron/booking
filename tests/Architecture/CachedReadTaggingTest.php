<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Cached Reads Carry an Invalidation Tag
|--------------------------------------------------------------------------
|
| A `Cache::remember()` call with no `Cache::tags()` lives in a store space
| no tagged `Cache::tags([...])->flush()` can ever reach — it can only read
| correct until the first write it should have reacted to, at which point
| it reads stale until its own TTL happens to expire. `qa-deep-findings.md`
| F-08 found exactly this in three public read paths; this is a content
| search, not a dependency-graph question, so it runs as a plain test
| rather than through the arch() DSL.
|
*/

test('no untagged Cache::remember() call exists in the public read or catalog-query paths', function (): void {
    // Architecture tests run outside the Laravel boot cycle (see tests/Pest.php),
    // so base_path() is unavailable — the project root is derived from this
    // file's own location instead: tests/Architecture -> tests -> root.
    $root = dirname(__DIR__, 2);

    $offenders = [];

    $files = (new Finder)
        ->files()
        ->in([
            $root.'/app/Http/Controllers/Public',
            $root.'/app/Services/Catalog',
        ])
        ->name('*.php');

    foreach ($files as $file) {
        // The tagged form is always `Cache::tags([...])->remember(` — the
        // literal substring `Cache::remember(` never appears in it, so its
        // presence alone is enough to flag an untagged call.
        if (preg_match('/\bCache::remember\(/', $file->getContents()) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
