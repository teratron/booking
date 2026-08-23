<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\SeoHealthDashboard;
use App\Models\User;
use App\Services\Seo\SeoHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| SEO Administration — Health Warnings
|--------------------------------------------------------------------------
|
| Six checks against a catalog this size nobody can browse by hand: missing
| title, missing description, over-length title, page excluded from
| indexing, duplicate canonical address, and missing translation. Each is
| exercised against a deliberately broken object-type fixture — the
| cheapest translatable entity {@see SeoHealthReport} scans — and the
| dashboard itself is permission-gated on the SEO grant.
|
*/

function seoHealthLanguages(): void
{
    DB::table('languages')->insert([
        ['code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);
}

function seoHealthObjectType(string $key): int
{
    return DB::table('object_types')->insertGetId([
        'key' => $key,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function seoAdminActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('flags an entity missing an SEO title', function (): void {
    seoHealthLanguages();
    $typeId = seoHealthObjectType('seo_health_missing_title');
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'seo-health-missing-title',
        'seo_title' => null, 'seo_description' => 'A place to stay.',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $warnings = app(SeoHealthReport::class)->warnings();

    expect(collect($warnings['missing_title'])->pluck('entityType'))->toContain('object_type');
});

it('flags an entity missing an SEO description', function (): void {
    seoHealthLanguages();
    $typeId = seoHealthObjectType('seo_health_missing_description');
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'seo-health-missing-description',
        'seo_title' => 'Hotels', 'seo_description' => '   ',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $warnings = app(SeoHealthReport::class)->warnings();

    expect(collect($warnings['missing_description'])->pluck('entityType'))->toContain('object_type');
});

it('flags an SEO title over 60 characters', function (): void {
    seoHealthLanguages();
    $typeId = seoHealthObjectType('seo_health_over_length');
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'seo-health-over-length',
        'seo_title' => str_repeat('a', 61), 'seo_description' => 'A place to stay.',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $warnings = app(SeoHealthReport::class)->warnings();

    expect(collect($warnings['over_length_title'])->pluck('entityType'))->toContain('object_type');
});

it('flags an entity excluded from indexing', function (): void {
    seoHealthLanguages();
    $typeId = seoHealthObjectType('seo_health_excluded');
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'seo-health-excluded',
        'seo_title' => 'Hotels', 'seo_description' => 'A place to stay.', 'seo_indexable' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $warnings = app(SeoHealthReport::class)->warnings();

    expect(collect($warnings['excluded_from_indexing'])->pluck('entityType'))->toContain('object_type');
});

it('flags two entities sharing the same canonical address', function (): void {
    seoHealthLanguages();
    $typeA = seoHealthObjectType('seo_health_duplicate_a');
    $typeB = seoHealthObjectType('seo_health_duplicate_b');
    DB::table('object_type_translations')->insert([
        [
            'object_type_id' => $typeA, 'locale' => 'en', 'name' => 'Hotel A', 'slug' => 'seo-health-duplicate-a',
            'seo_canonical_url' => '/en/duplicate-address', 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'object_type_id' => $typeB, 'locale' => 'en', 'name' => 'Hotel B', 'slug' => 'seo-health-duplicate-b',
            'seo_canonical_url' => '/en/duplicate-address', 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $warnings = app(SeoHealthReport::class)->warnings();

    expect($warnings['duplicate_address'])->toHaveCount(2);
});

it('flags an entity missing a translation in an active language', function (): void {
    seoHealthLanguages();
    $typeId = seoHealthObjectType('seo_health_missing_translation');
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'seo-health-missing-translation',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $warnings = app(SeoHealthReport::class)->warnings();

    expect($warnings['missing_translation'])->not->toBeEmpty();
});

it('gates the SEO health dashboard behind the SEO grant', function (): void {
    $permitted = seoAdminActor(['admin_panel_access', 'seo.view'], 'seo_dashboard_permitted');
    $refused = seoAdminActor(['admin_panel_access'], 'seo_dashboard_refused');

    $this->actingAs($permitted)->get(SeoHealthDashboard::getUrl(panel: 'admin'))->assertSuccessful();
    $this->actingAs($refused)->get(SeoHealthDashboard::getUrl(panel: 'admin'))->assertForbidden();
});

it('shows the map-tile-key-missing banner when no key is configured, and hides it once one is', function (): void {
    // F-16: MapTileConfigResolver read integrations.map_tile_key only from
    // the settings table, whose registry default is an empty string — the
    // map is dead out of the box, and nothing on this dashboard said so.
    config(['booking.integrations.map_tile_key' => '']);
    $actor = seoAdminActor(['admin_panel_access', 'seo.view'], 'seo_map_key_missing');

    $this->actingAs($actor)->get(SeoHealthDashboard::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee(__('panel.seo_health.map_tile_key_missing'));

    // Written directly rather than through SettingsRepository::set(): this
    // key is critical (chief-administrator-only), and the write path
    // itself is not what this test is about. SettingsRepository caches its
    // resolved values for the process lifetime and only invalidates that
    // cache from within set() (see its own docblock), so a direct write
    // must drop the same key or the second request below would still see
    // the first request's cached "no key" answer.
    DB::table('settings')->insert([
        'key' => 'integrations.map_tile_key', 'value' => json_encode('a-real-key'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    Cache::forget('portal.settings');

    $this->actingAs($actor)->get(SeoHealthDashboard::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertDontSee(__('panel.seo_health.map_tile_key_missing'));
});
