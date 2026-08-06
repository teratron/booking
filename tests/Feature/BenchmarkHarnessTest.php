<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Benchmark Harness
|--------------------------------------------------------------------------
|
| Tagged `slow` for the same reason as DemoVolumeSeederTest — this seeds
| over 175,000 rows for real before measuring anything. Excluded from
| `composer test`/`quality`; run explicitly via `composer test:slow`.
|
*/

test('bench:run measures both hot-path queries and exits successfully at seeded volume', function (): void {
    $this->seed();
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoVolumeSeeder', '--force' => true]);

    $exitCode = Artisan::call('bench:run');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Catalog ranking query');
    expect($output)->toContain('Territory subtree expansion');
    expect($output)->not->toContain('FAIL');
})->group('slow');

test('bench:run refuses to report against unrealistically low volume', function (): void {
    $this->seed();

    $exitCode = Artisan::call('bench:run');

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('too low to benchmark meaningfully');
});
