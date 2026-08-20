<?php

declare(strict_types=1);

use App\Services\Documentation\OperationsDocumentationTree;

/*
|--------------------------------------------------------------------------
| Operator Documentation Parity — At Rest
|--------------------------------------------------------------------------
|
| The three renderings of every operator procedure — English, Russian, and
| the machine-addressed rendering — must exist as the same set of file
| stems. This is the "at rest" half of the parity guarantee: it fails the
| moment any rendering is missing a file the others have, regardless of
| which change introduced the gap. The "at the moment of change" half runs
| as a pull-request workflow step instead (App\Console\Commands\
| CheckOperationsDocumentationParity) — a content-scanning test here has no
| access to a pull request's own diff.
|
| Architecture tests run outside the Laravel boot cycle (see
| tests/Pest.php), so base_path() is unavailable — the project root is
| derived from this file's own location, matching ContainmentTest.php's own
| convention.
|
*/

test('the three operator-documentation trees hold the same procedure set', function (): void {
    $root = dirname(__DIR__, 2);
    $tree = new OperationsDocumentationTree("{$root}/docs/operations");

    $stemsByTree = $tree->stemsByTree();

    expect($stemsByTree['en'])->not->toBe([]) // a real deliverable, not an accidentally-empty tree
        ->and($stemsByTree['ru'])->toBe($stemsByTree['en'])
        ->and($stemsByTree['agent'])->toBe($stemsByTree['en']);
});
