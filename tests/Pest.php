<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Territory;
use App\Services\Seo\PublicUrlGenerator;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The base Laravel TestCase applies to Feature and Unit tests only. Arch
| tests run outside a Laravel application boot cycle — they inspect classes
| statically — so binding TestCase to them would boot a framework nothing
| in an arch assertion needs.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Public URL Helpers
|--------------------------------------------------------------------------
|
| Every public test that visits a territory or object page builds its URL
| through the same generator product code uses, rather than reconstructing
| the grammar per test file. Declared once here — Pest loads every test
| file into one global scope, so a per-file declaration of the same
| function name is a redeclaration fatal, not an isolated duplicate.
|
*/

function publicObjectUrl(Object_ $object): string
{
    return (string) app(PublicUrlGenerator::class)->objectUrl($object);
}

function publicTerritoryUrl(Territory $territory): string
{
    return (string) app(PublicUrlGenerator::class)->territoryUrl($territory);
}

/*
|--------------------------------------------------------------------------
| Exporter File-to-Class Resolution
|--------------------------------------------------------------------------
|
| Three suites walk `app/Filament` for `*Exporter.php` files and resolve
| each one back to its class name by trimming `base_path('app')` off the
| front of the absolute path. On Windows, `RecursiveDirectoryIterator`
| mixes the literal `/` embedded in the `base_path('app/Filament')` call
| that constructs it with the `\` it appends for every nested directory —
| so an anchor built from a bare `DIRECTORY_SEPARATOR` never matches, and
| the whole absolute path (including the drive letter) gets glued onto
| `App\`. Normalising both sides to `/` before matching, then converting
| the trimmed remainder to the class separator, works on every OS.
|
*/

function exporterClassFromPath(string $path): string
{
    $anchor = str_replace('\\', '/', base_path('app')).'/';
    $relative = Str::of(str_replace('\\', '/', $path))
        ->after($anchor)
        ->replace('/', '\\')
        ->beforeLast('.php');

    return 'App\\'.$relative;
}
