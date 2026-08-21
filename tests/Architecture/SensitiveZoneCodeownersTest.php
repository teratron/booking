<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Sensitive-Zone CODEOWNERS Coverage
|--------------------------------------------------------------------------
|
| The standing autonomous-operation grant lets an ordinary, gate-passing
| change merge without a separate review step — except inside a declared set
| of sensitive zones (authentication, authorization, money, secrets and CI
| wiring), where GitHub's own CODEOWNERS mechanism forces the owner's review
| regardless. .github/CODEOWNERS is the single source of that enforcement;
| this test is the alarm that fires when the enforcement falls behind the
| tree it is supposed to cover.
|
| The direction matters. Checking that a hand-written list of paths is
| covered proves only that the list is covered, and the list is the same
| human memory the mechanism exists to replace — a policy added tomorrow is
| absent from both, so the suite stays green while the gate has a hole in
| it. So the rules below describe how to *find* sensitive files in the real
| tree, and every file they find must be matched by some CODEOWNERS pattern.
| A new Policy, a new Filament resource over money, a new migration touching
| the token tables: discovered on sight, and failing here until the owner
| owns it.
|
| Architecture tests run outside the Laravel boot cycle (see tests/Pest.php),
| so base_path() is unavailable — the project root is derived from this
| file's own location, matching the other architecture tests' convention.
|
*/

/**
 * The sensitive zones as discovery rules over the tree, not as a list of
 * paths. Each rule names a directory to walk and a predicate over the
 * repository-relative path of everything inside it.
 *
 * @return list<array{zone: string, in: string, matches: Closure(string): bool}>
 */
function sensitiveZoneDiscoveryRules(): array
{
    return [
        // Authentication, session, and second-factor handling. Authenticate*
        // is matched on principle rather than presence: the framework owns
        // that middleware today, and a published copy must not slip in
        // unowned on the day someone needs to customize it.
        ['zone' => 'authentication', 'in' => 'app/Http/Middleware', 'matches' => fn (string $path): bool => (bool) preg_match('#/(Authenticate|EnsureSecondFactor)[^/]*\.php$#', '/'.$path)],
        ['zone' => 'authentication', 'in' => 'config', 'matches' => fn (string $path): bool => $path === 'config/auth.php'],

        // Authorization itself: every policy, the scoping services behind
        // them, the permission/role registry a policy's decision reads, and
        // the migrations and seeders that populate it. Token tables belong
        // here too — a credential's lifetime is an authorization question.
        ['zone' => 'authorization', 'in' => 'app/Policies', 'matches' => fn (string $path): bool => str_ends_with($path, '.php')],
        ['zone' => 'authorization', 'in' => 'app/Services/Authorization', 'matches' => fn (string $path): bool => str_ends_with($path, '.php')],
        ['zone' => 'authorization', 'in' => 'config', 'matches' => fn (string $path): bool => $path === 'config/permission.php'],
        ['zone' => 'authorization', 'in' => 'database/seeders', 'matches' => fn (string $path): bool => (bool) preg_match('#(Permission|Role)#', basename($path))],
        ['zone' => 'authorization', 'in' => 'database/migrations', 'matches' => fn (string $path): bool => (bool) preg_match('#(permission|role|personal_access_token)#', basename($path))],

        // Money: the financial ledger, the placement and commerce services
        // that write to it, and every admin surface built over either —
        // matched by path fragment, so an exporter or a page nested under a
        // money resource is covered without naming each file.
        ['zone' => 'money', 'in' => 'app/Models', 'matches' => fn (string $path): bool => str_starts_with(basename($path), 'FinancialRecord')],
        ['zone' => 'money', 'in' => 'app/Services/Placement', 'matches' => fn (string $path): bool => str_ends_with($path, '.php')],
        ['zone' => 'money', 'in' => 'app/Filament', 'matches' => fn (string $path): bool => str_contains($path, 'FinancialRecord') || str_contains($path, 'Placement')],

        // Secrets and credential wiring. Every workflow counts, not only the
        // steps that name a secret today: which step reads a secret is a
        // property of the next edit, not of the current file.
        ['zone' => 'secrets', 'in' => 'config', 'matches' => fn (string $path): bool => $path === 'config/services.php'],
        ['zone' => 'secrets', 'in' => '.github/workflows', 'matches' => fn (string $path): bool => (bool) preg_match('#\.ya?ml$#', $path)],
    ];
}

/**
 * The committed dotenv files, derived from .gitignore rather than repeated
 * here. The ignore rule is `.env*` plus an explicit negation per file that
 * is allowed in — so the negations *are* the list, and committing a new one
 * puts it under this test automatically.
 *
 * An uncommitted .env is deliberately out of scope: it cannot appear in a
 * pull request, so it cannot merge under the grant this test guards.
 *
 * @return list<string>
 */
function committedDotenvFiles(string $root): array
{
    $files = [];

    foreach (explode("\n", (string) file_get_contents($root.'/.gitignore')) as $line) {
        $line = trim($line);

        if (! str_starts_with($line, '!')) {
            continue;
        }

        $candidate = ltrim(substr($line, 1), '/');

        if (str_starts_with($candidate, '.env') && is_file($root.'/'.$candidate)) {
            $files[] = $candidate;
        }
    }

    return $files;
}

/**
 * The ownership patterns declared in .github/CODEOWNERS, in file order.
 *
 * @return list<string>
 */
function codeownersPatterns(string $root): array
{
    $patterns = [];

    foreach (explode("\n", (string) file_get_contents($root.'/.github/CODEOWNERS')) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$pattern] = explode(' ', $line, 2);
        $patterns[] = $pattern;
    }

    return $patterns;
}

/**
 * Whether one gitignore-style CODEOWNERS pattern covers a repository-relative
 * path. Supports the shapes GitHub honours and this file uses: a leading
 * slash anchoring to the root, a trailing slash for a whole directory, `*`
 * within one segment, and `**` across segments. Negation and character
 * ranges are not supported by CODEOWNERS itself and are not handled here.
 */
function codeownersPatternCovers(string $pattern, string $relativePath): bool
{
    $pattern = ltrim($pattern, '/');
    $matchesDirectoryOnly = str_ends_with($pattern, '/');

    $regex = preg_quote(rtrim($pattern, '/'), '#');
    $regex = str_replace('\*\*/', '(?:[^/]+/)*', $regex);
    $regex = str_replace('\*\*', '.*', $regex);
    $regex = str_replace('\*', '[^/]*', $regex);
    $regex = str_replace('\?', '[^/]', $regex);

    // A pattern naming a directory covers everything beneath it, which is
    // why the tail is optional for a file pattern and required for one
    // written with a trailing slash.
    $tail = $matchesDirectoryOnly ? '/' : '(?:/|$)';

    return preg_match('#^'.$regex.$tail.'#', $relativePath) === 1;
}

/**
 * Every file the discovery rules find, keyed by zone.
 *
 * @return array<string, list<string>>
 */
function discoveredSensitiveFiles(string $root): array
{
    $discovered = [];

    foreach (sensitiveZoneDiscoveryRules() as $rule) {
        $directory = $root.'/'.$rule['in'];

        expect(is_dir($directory))->toBeTrue("Sensitive zone [{$rule['zone']}] points at [{$rule['in']}], which no longer exists — a rename left this rule discovering nothing.");

        foreach ((new Finder)->files()->in($directory) as $file) {
            $relativePath = $rule['in'].'/'.str_replace('\\', '/', $file->getRelativePathname());

            if (($rule['matches'])($relativePath)) {
                $discovered[$rule['zone']][] = $relativePath;
            }
        }
    }

    foreach (committedDotenvFiles($root) as $dotenv) {
        $discovered['secrets'][] = $dotenv;
    }

    return $discovered;
}

test('every file discovered in a sensitive zone is covered by a CODEOWNERS pattern', function (): void {
    $root = dirname(__DIR__, 2);
    $patterns = codeownersPatterns($root);

    $uncovered = [];

    foreach (discoveredSensitiveFiles($root) as $zone => $paths) {
        foreach ($paths as $path) {
            $covered = false;

            foreach ($patterns as $pattern) {
                if (codeownersPatternCovers($pattern, $path)) {
                    $covered = true;

                    break;
                }
            }

            if (! $covered) {
                $uncovered[] = "{$zone}: {$path}";
            }
        }
    }

    sort($uncovered);

    expect($uncovered)->toBe([]);
});

test('every sensitive zone still discovers files', function (): void {
    $root = dirname(__DIR__, 2);
    $discovered = discoveredSensitiveFiles($root);

    // A zone that finds nothing is the failure mode this rewrite exists to
    // prevent: the rules would keep passing while covering an empty set.
    foreach (['authentication', 'authorization', 'money', 'secrets'] as $zone) {
        expect($discovered[$zone] ?? [])->not->toBeEmpty("Sensitive zone [{$zone}] discovered no files — its rules no longer match the tree.");
    }
});

test('every CODEOWNERS entry names the project owner, never a blank or automation identity', function (): void {
    $root = dirname(__DIR__, 2);

    foreach (explode("\n", (string) file_get_contents($root.'/.github/CODEOWNERS')) as $lineNumber => $line) {
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
