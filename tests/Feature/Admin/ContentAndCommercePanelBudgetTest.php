<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\AnalyticsReport;
use App\Filament\Admin\Pages\CommerceReports;
use App\Filament\Admin\Pages\NotificationBroadcast;
use App\Models\User;
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
| Content & Commerce Panel Query Budget Under Seeded Volume
|--------------------------------------------------------------------------
|
| `PanelQueryBudgetTest` sweeps every registered Filament *resource*
| dynamically, but `Filament::getPanel('admin')->getResources()` never
| returns custom *pages* — so the three report/broadcast pages this track
| added (commerce reports, the analytics report, the administrator
| broadcast composer) are invisible to that loop. Each renders its own
| filter dropdowns (country, territory, object type, language, banner)
| against the same seeded volume, which is exactly the shape that turns a
| missing eager-load into a per-row query. Tagged `slow` and self-contained:
| it seeds `DemoVolumeSeeder` independently rather than sharing state with
| any other slow-tagged test file.
|
*/

function contentCommerceBudgetActor(): User
{
    $permissions = [
        'admin_panel_access', 'finance.view', 'analytics.view', 'analytics.export', 'notification.create',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('content_commerce_budget_actor', 'web');
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
function measureContentCommercePage(User $actor, string $url): array
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

it('resolves the commerce reports, analytics report, and broadcast pages within the query budget at seeded volume', function (): void {
    $this->seed();
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoVolumeSeeder', '--force' => true]);

    $objectCount = DB::table('objects')->count();
    $territoryCount = DB::table('territories')->count();

    // Same guard the resource sweep applies — a fixture too small never
    // exposes an N+1 pattern in the territory/object-type dropdown queries
    // these pages build.
    expect($objectCount)->toBeGreaterThanOrEqual(50_000)
        ->and($territoryCount)->toBeGreaterThanOrEqual(3_000);

    $actor = contentCommerceBudgetActor();

    $pages = [
        CommerceReports::class => CommerceReports::getUrl(panel: 'admin'),
        AnalyticsReport::class => AnalyticsReport::getUrl(panel: 'admin'),
        NotificationBroadcast::class => NotificationBroadcast::getUrl(panel: 'admin'),
    ];

    $findings = [];

    foreach ($pages as $pageClass => $url) {
        $result = measureContentCommercePage($actor, $url);
        $findings[$pageClass] = $result;

        expect($result['status'])->toBe(200, "{$pageClass} did not render successfully ({$result['status']}).");
        expect($result['queries'])->toBeLessThanOrEqual(30, "{$pageClass} issued {$result['queries']} queries against a 30-query budget.");
    }

    // Findings are recorded as numbers, not a pass/fail claim — this line
    // is the task's own evidence, read from the actual run rather than
    // hand-typed after the fact.
    fwrite(STDERR, "\n".json_encode(['seeded' => ['objects' => $objectCount, 'territories' => $territoryCount], 'findings' => $findings], JSON_PRETTY_PRINT)."\n");
})->group('slow');
