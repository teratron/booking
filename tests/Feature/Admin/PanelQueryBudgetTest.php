<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Panel Query Budget Under Seeded Volume
|--------------------------------------------------------------------------
|
| Twelve fixtures and 50,000 seeded objects behave differently against the
| same query — a `belongsTo` eager-loaded once looks identical to one
| resolved per row until the row count is the one the launch countries will
| actually carry. Tagged `slow`: this seeds the full demo volume (52,800
| objects, 6,270 territories, ~175,000 rows in total) before measuring
| anything, so it is excluded from `composer test`/`quality` and run
| explicitly via `composer test:slow`.
|
*/

function queryBudgetActor(): User
{
    $permissions = [
        'admin_panel_access', 'object.view', 'user.view', 'geography.view',
        'moderation.view', 'settings.view', 'audit.view',
        // content/commerce/finance/advertising were added by the track that
        // introduced the eleven resources this sweep now iterates — without
        // these the dynamic loop below 403s before it ever reaches a query
        // count, which hides a real budget regression behind an unrelated
        // authorization failure.
        'content.view', 'commerce.view', 'finance.view', 'advertising.view',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('query_budget_actor', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Cache::flush();

    return $user->fresh();
}

/** @return array{status: int, queries: int} */
function measurePage(User $actor, string $url): array
{
    Cache::flush();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = test()->actingAs($actor)->get($url);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    return ['status' => $response->getStatusCode(), 'queries' => $queries];
}

it('resolves every panel resource list, the dashboard, the moderation queue, and the action journal within the query budget at seeded volume', function (): void {
    $this->seed();
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoVolumeSeeder', '--force' => true]);

    $objectCount = DB::table('objects')->count();
    $territoryCount = DB::table('territories')->count();

    // The task this proves nothing against a fixture too small to expose an
    // N+1 pattern — the same guard `bench:run` already applies.
    expect($objectCount)->toBeGreaterThanOrEqual(50_000)
        ->and($territoryCount)->toBeGreaterThanOrEqual(3_000);

    $actor = queryBudgetActor();

    $resources = Filament::getPanel('admin')->getResources();

    expect($resources)->not->toBeEmpty();

    $findings = [];

    foreach ($resources as $resourceClass) {
        $result = measurePage($actor, $resourceClass::getUrl('index', panel: 'admin'));
        $findings[$resourceClass] = $result;

        expect($result['status'])->toBe(200, "{$resourceClass}'s list page did not render successfully ({$result['status']}).");
        expect($result['queries'])->toBeLessThanOrEqual(30, "{$resourceClass}'s list page issued {$result['queries']} queries against a 30-query budget.");
    }

    // The dashboard carries two fixed, non-scaling queries beyond the
    // per-resource budget — the interface catalog override overlay and the
    // primary-language fallback lookup, both already established at this
    // exact number in `AdminDashboardTest`. Neither grows with data volume,
    // so this is a one-time budget step, not the start of a per-row creep.
    $dashboard = measurePage($actor, '/'.config('booking.panels.admin.path'));
    $findings['dashboard'] = $dashboard;

    expect($dashboard['status'])->toBe(200)
        ->and($dashboard['queries'])->toBeLessThanOrEqual(32);

    // Findings are recorded as numbers, not a pass/fail claim — this line
    // is the task's own evidence, read from the actual run rather than
    // hand-typed into the phase file after the fact.
    fwrite(STDERR, "\n".json_encode(['seeded' => ['objects' => $objectCount, 'territories' => $territoryCount], 'findings' => $findings], JSON_PRETTY_PRINT)."\n");
})->group('slow');
