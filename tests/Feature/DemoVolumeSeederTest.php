<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Realistic-Volume Demo Seeder
|--------------------------------------------------------------------------
|
| Tagged `slow` and excluded from the default `composer test`/`quality`
| run — this seeds over 175,000 rows for real, which is the whole point
| (a dozen fixtures measure nothing), but paying that cost on every
| quality-gate invocation would slow down every later task's loop for the
| rest of the project. Run explicitly via `composer test:slow`.
|
*/

test('the demo volume seeder produces catalog-ranking benchmark fixtures at realistic volume', function (): void {
    $this->seed();

    $previousLimit = ini_set('memory_limit', '128M');

    try {
        $exitCode = Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\DemoVolumeSeeder',
            '--force' => true,
        ]);
    } finally {
        ini_set('memory_limit', $previousLimit);
    }

    // A real, enforced constraint, not a documented one: if chunking
    // regresses back to accumulating the whole 175,000-row insert in PHP
    // memory before flushing, this fails with a fatal "Allowed memory size
    // exhausted" before reaching the assertions below.
    expect($exitCode)->toBe(0);

    $objectCount = DB::table('objects')->count();
    $leafTerritoryCount = DB::table('territories')
        ->whereIn('level_id', DB::table('territory_levels')->where('depth_rank', 4)->pluck('id'))
        ->count();

    expect($objectCount)->toBeGreaterThanOrEqual(50_000);
    expect($leafTerritoryCount)->toBeGreaterThanOrEqual(3_000);

    // Every object has translations in both active languages and a
    // populated geom — not merely a nonzero count.
    $activeLanguageCount = DB::table('languages')->where('is_active', true)->count();

    $objectsWithFullTranslations = DB::table('object_translations')
        ->select('object_id')
        ->groupBy('object_id')
        ->havingRaw('count(*) = ?', [$activeLanguageCount])
        ->count();

    $objectsWithGeom = DB::table('objects')->whereNotNull('geom')->count();

    expect($objectsWithFullTranslations)->toBe($objectCount);
    expect($objectsWithGeom)->toBe($objectCount);
})->group('slow');
