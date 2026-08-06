<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Admin Dashboard
|--------------------------------------------------------------------------
|
| Two claims are worth asserting and neither is visible by looking at the
| screen: a counter obeys the viewer's scope, and the finance block is absent
| from the response rather than hidden in it. A headline figure that ignores
| scope discloses precisely what the list narrowing was built to prevent.
|
*/

function dashboardFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $countries = [];
    foreach (['MD', 'GE'] as $code) {
        $countries[$code] = DB::table('countries')->insertGetId([
            'code' => $code, 'currency' => 'EUR', 'phone_code' => '+000',
            'primary_language_id' => $languageId, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = User::factory()->create();
    $territories = [];

    foreach ($countries as $code => $countryId) {
        $levelId = DB::table('territory_levels')->insertGetId([
            'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $territories[$code] = DB::table('territories')->insertGetId([
            'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Two published in Moldova, one in Georgia, plus one Moldovan object
    // awaiting review — enough to tell a scoped count from a global one.
    $rows = [
        ['MD', 'published', null],
        ['MD', 'published', null],
        ['MD', 'draft', 'pending'],
        ['GE', 'published', null],
    ];

    foreach ($rows as [$code, $status, $moderation]) {
        DB::table('objects')->insert([
            'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
            'object_type_id' => $typeId, 'territory_id' => $territories[$code],
            'country_id' => $countries[$code], 'status' => $status,
            'moderation_status' => $moderation,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return ['countries' => $countries, 'owner' => $owner];
}

function dashboardActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => $scopeKind, 'scope_reference_id' => $reference,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Cache::flush();

    return $user->fresh();
}

it('counts only what the viewer scope reaches', function (): void {
    $fixture = dashboardFixture();

    $unrestricted = dashboardActor(['object.view'], 'none', null, 'unrestricted');
    expect(app(DashboardMetrics::class)->operational($unrestricted)['total'])->toBe(4);

    $moldova = dashboardActor(['object.view'], 'country', $fixture['countries']['MD'], 'moldova_only');
    $counts = app(DashboardMetrics::class)->operational($moldova);

    expect($counts['total'])->toBe(3)
        ->and($counts['published'])->toBe(2)
        ->and($counts['pending_moderation'])->toBe(1);
});

it('counts objects awaiting review, which the public query cannot see at all', function (): void {
    dashboardFixture();
    $actor = dashboardActor(['object.view'], 'none', null, 'unrestricted');

    // A dashboard built on the plain model query would report zero pending
    // work — the moderation global scope removes exactly the rows this figure
    // exists to surface.
    expect(app(DashboardMetrics::class)->operational($actor)['pending_moderation'])->toBe(1)
        ->and(Object_::query()->where('moderation_status', 'pending')->count())->toBe(0);
});

it('keeps the finance block out of the response for an account without the grant', function (): void {
    dashboardFixture();

    $withFinance = dashboardActor(
        ['admin_panel_access', 'object.view', 'financial_access'],
        'none', null, 'finance_manager',
    );
    $withoutFinance = dashboardActor(
        ['admin_panel_access', 'object.view'],
        'none', null, 'content_manager',
    );

    $url = '/'.config('booking.panels.admin.path');

    $this->actingAs($withFinance)->get($url)
        ->assertSuccessful()
        ->assertSee(__('panel.dashboard.active_placements'));

    // Absent from the body, not merely hidden by styling: a control removed
    // from the markup cannot be revealed by editing the page.
    $this->actingAs($withoutFinance)->get($url)
        ->assertSuccessful()
        ->assertDontSee(__('panel.dashboard.active_placements'));
});

it('resolves the dashboard within the phase query budget', function (): void {
    dashboardFixture();
    $actor = dashboardActor(
        ['admin_panel_access', 'object.view', 'financial_access'],
        'none', null, 'finance_manager',
    );

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($actor)->get('/'.config('booking.panels.admin.path'))->assertSuccessful();

    expect($queries)->toBeLessThanOrEqual(30);
});
