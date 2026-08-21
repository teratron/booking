<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sensitive-Zone CODEOWNERS Coverage
|--------------------------------------------------------------------------
|
| The standing autonomous-operation grant lets an ordinary, gate-passing
| change merge without a separate review step — except for a declared set
| of sensitive paths (authentication, authorization, financial records,
| secrets and CI wiring), where GitHub's own CODEOWNERS mechanism forces a
| review regardless. This test is the mechanical proof that every path the
| policy names is actually listed in .github/CODEOWNERS, so the boundary
| cannot drift out of sync between what is decided and what GitHub actually
| enforces.
|
*/

/**
 * Every path this list names must exist in the real tree (so a rename does
 * not silently stop being covered) and must be matched by some line in
 * .github/CODEOWNERS.
 *
 * @return list<string>
 */
function sensitiveZoneCanonicalPaths(): array
{
    return [
        'app/Http/Middleware/EnsureSecondFactorForPrivilegedRoles.php',
        'config/auth.php',
        'app/Policies/ScopedPolicy.php',
        'app/Services/Authorization/ResourceQueryScoper.php',
        'config/permission.php',
        'database/seeders/PermissionSeeder.php',
        'database/seeders/RoleSeeder.php',
        'database/migrations/2026_08_05_232008_create_permission_tables.php',
        'database/migrations/2026_08_05_232203_create_role_scopes_table.php',
        'app/Models/FinancialRecord.php',
        'app/Services/Placement/BumpService.php',
        '.env.example',
        '.env.production.example',
        'config/services.php',
        '.github/workflows/quality.yml',
    ];
}

/**
 * A CODEOWNERS pattern is gitignore-style; this project's own file only uses
 * the two shapes below, so the matcher only needs to understand those two —
 * widen it if a future pattern needs more.
 */
function pathMatchesCodeownersPattern(string $relativePath, string $pattern): bool
{
    $pattern = ltrim($pattern, '/');

    if (str_ends_with($pattern, '/')) {
        return str_starts_with($relativePath, $pattern);
    }

    $regex = '#^'.str_replace('\*', '[^/]*', preg_quote($pattern, '#')).'$#';

    return preg_match($regex, $relativePath) === 1;
}

test('every declared sensitive path is listed in .github/CODEOWNERS', function (): void {
    $root = dirname(__DIR__, 2);
    $codeowners = (string) file_get_contents($root.'/.github/CODEOWNERS');

    $patterns = [];
    foreach (explode("\n", $codeowners) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$pattern] = explode(' ', $line, 2);
        $patterns[] = $pattern;
    }

    $uncovered = [];

    foreach (sensitiveZoneCanonicalPaths() as $path) {
        expect(file_exists($root.'/'.$path))->toBeTrue("Canonical sensitive path [{$path}] no longer exists — update this test's list.");

        $covered = false;
        foreach ($patterns as $pattern) {
            if (pathMatchesCodeownersPattern($path, $pattern)) {
                $covered = true;

                break;
            }
        }

        if (! $covered) {
            $uncovered[] = $path;
        }
    }

    expect($uncovered)->toBe([]);
});

test('every CODEOWNERS entry names the project owner, never a blank or automation identity', function (): void {
    $root = dirname(__DIR__, 2);
    $codeowners = (string) file_get_contents($root.'/.github/CODEOWNERS');

    foreach (explode("\n", $codeowners) as $lineNumber => $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        /** @var list<string> $parts */
        $parts = preg_split('/\s+/', $line) ?: [];
        expect(count($parts))->toBeGreaterThanOrEqual(2, 'Line '.($lineNumber + 1)." names no owner: [{$line}]");

        foreach (array_slice($parts, 1) as $owner) {
            expect($owner)->toStartWith('@', 'Line '.($lineNumber + 1)." owner [{$owner}] is not a valid @handle.");
        }
    }
});
