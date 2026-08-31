<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Filament Selects Never Hydrate an Unbounded Table
|--------------------------------------------------------------------------
|
| A `Select::make(...)->options(fn () => Model::query()->pluck(...)->all())`
| loads the whole table into the form payload on every render. For the
| three registries that grow with the catalog and the userbase — objects,
| territories, and the staff/owner users table — that pathology hydrated
| tens of thousands of rows (and their translations) into one dropdown, a
| multi-second render measured in megabytes of HTML. `SearchableModelSelect`
| exists so those three are always reached through server-side search with
| a bounded result set instead.
|
| This is a text-pattern question, not a dependency-graph one, so it runs
| as a plain content scan rather than through the arch() DSL — the same
| shape as ContainmentTest and CachedReadTaggingTest. Architecture tests
| run outside the Laravel boot cycle (see tests/Pest.php), so the project
| root is derived from this file's own location.
|
*/

test('no Filament ->options() call hydrates an unbounded objects/territories/users table', function (): void {
    $root = dirname(__DIR__, 2);

    // The registries whose row count is a function of catalog size or
    // traffic — never a fixed list an administrator maintains by hand.
    $unboundedModels = ['Object_', 'Territory', 'User'];

    // A narrowing that makes the result set bounded in practice: an
    // explicit key filter, a permission/role filter, a single-row lookup,
    // an explicit cap, or the sanctioned server-side-search pair.
    $boundedForms = ['->limit(', 'getSearchResultsUsing', '->whereIn(', '->whereHas(', '->permission(', '->role(', '->find(', '->whereKey'];

    $enumerators = ['->pluck(', '->get(', '->all(', '::all('];

    $containsAny = static fn (string $haystack, array $needles): bool => array_any(
        $needles,
        static fn (string $needle): bool => str_contains($haystack, $needle),
    );

    $offenders = [];

    $files = (new Finder)
        ->files()
        ->in($root.'/app/Filament')
        ->name('*.php');

    foreach ($files as $file) {
        $contents = $file->getContents();
        $offset = 0;

        while (($start = strpos($contents, '->options(', $offset)) !== false) {
            // Walk from the opening paren of ->options( to its match,
            // tracking depth, so the captured expression is exactly that
            // one argument and not the rest of the chain.
            $depth = 0;
            $end = $start + strlen('->options(') - 1;

            for ($i = $end, $len = strlen($contents); $i < $len; $i++) {
                $char = $contents[$i];

                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;

                    if ($depth === 0) {
                        $end = $i;

                        break;
                    }
                }
            }

            $expression = substr($contents, $start, $end - $start + 1);
            $offset = $end + 1;

            $touchesUnbounded = array_any(
                $unboundedModels,
                static fn (string $model): bool => preg_match(
                    '/\b'.preg_quote($model, '/').'::(?:query|where|with|all)\b/',
                    $expression,
                ) === 1,
            );

            if (! $touchesUnbounded) {
                continue;
            }

            if (! $containsAny($expression, $enumerators) || $containsAny($expression, $boundedForms)) {
                continue;
            }

            $line = substr_count(substr($contents, 0, $start), "\n") + 1;
            $offenders[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.$line;
        }
    }

    expect($offenders)->toBe([], sprintf(
        'These Filament ->options() calls load an unbounded table in full. Route them through '.
        "App\\Filament\\Support\\SearchableModelSelect (objects/territories/users) instead:\n%s",
        implode("\n", $offenders),
    ));
});
