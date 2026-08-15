<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Territory;
use App\Services\Seo\PublicUrlGenerator;
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
