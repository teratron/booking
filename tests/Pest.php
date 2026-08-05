<?php

declare(strict_types=1);

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
