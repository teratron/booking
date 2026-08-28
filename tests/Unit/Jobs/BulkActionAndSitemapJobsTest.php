<?php

declare(strict_types=1);

use App\Exceptions\BulkSelectionScopeException;
use App\Jobs\ExecuteObjectBulkActionJob;
use App\Jobs\GenerateSitemapsJob;
use App\Models\User;
use App\Services\Objects\ObjectBulkActionService;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Queued Job Dispatch & Error Handling
|--------------------------------------------------------------------------
|
| ExecuteObjectBulkActionJob and GenerateSitemapsJob are both thin
| dispatchers — every business rule they lean on belongs to
| ObjectBulkActionService and SitemapBuilder, already covered by their own
| test suites. Both collaborators are `final`, which rules out a Mockery
| class-name double for either (Mockery refuses to subclass a final class,
| and an object handed to Mockery::mock() to work around that stops
| satisfying the method's own type hint) — this codebase's established
| answer for a final collaborator is to prove delegation through real,
| minimal, observable effects instead of an argument-capturing spy. What
| belongs to the job itself — its queue assignment, the order it resolves
| its own arguments in, and whether it lets a collaborator's exception
| escape uncaught — is what these tests exercise.
|
*/

function bulkActionJobGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryMd = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryUa = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelUa = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryUa, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryMd = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryUa = DB::table('territories')->insertGetId([
        'country_id' => $countryUa, 'level_id' => $levelUa, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeStay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryMd', 'countryUa', 'territoryMd', 'territoryUa', 'typeStay');
}

/**
 * @param  array<string, mixed>  $geo
 * @param  array<string, mixed>  $overrides
 */
function bulkActionJobSeedObject(array $geo, array $overrides = []): int
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['typeStay'],
        'territory_id' => $geo['territoryMd'],
        'country_id' => $geo['countryMd'],
        'status' => 'draft',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en',
        'name' => 'Object #'.$objectId, 'slug' => 'object-'.$objectId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $objectId;
}

/** @param  list<string>  $permissions */
function bulkActionJobActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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

    return $user->fresh();
}

// -----------------------------------------------------------------------
// ExecuteObjectBulkActionJob
// -----------------------------------------------------------------------

it('declares the shared "bulk" queue used by Filament\'s own import/export jobs', function (): void {
    $job = new ExecuteObjectBulkActionJob('change_status', [1], ['status' => 'hidden'], 1);

    expect($job->queue)->toBe('bulk');
});

it('resolves the actor before ever touching the service, so a nonexistent actor id fails without reaching it', function (): void {
    // An operation the service does not recognise: if the job's own actor
    // lookup did not run first, execute() would throw InvalidArgumentException
    // for the unknown operation instead of ModelNotFoundException — which
    // exception actually surfaces is itself the proof of the call order.
    $job = new ExecuteObjectBulkActionJob('not_a_real_operation', [1, 2], [], 999_999_999);

    expect(fn () => $job->handle(app(ObjectBulkActionService::class)))
        ->toThrow(ModelNotFoundException::class);
});

it('forwards the operation, the exact object ids, and the parameters to the service using the actor it resolved from actorId', function (): void {
    $geo = bulkActionJobGeography();
    $selected = bulkActionJobSeedObject($geo, ['status' => 'draft']);
    $untouched = bulkActionJobSeedObject($geo, ['status' => 'draft']);
    // Scoped to country MD only: if the job passed some other actor than the
    // one actorId names, or dropped the scope along the way, this operation
    // would fail with BulkSelectionScopeException instead of succeeding.
    $actor = bulkActionJobActor(['admin_panel_access', 'object.view', 'object.edit'], 'country', $geo['countryMd'], 'bulk_job_md_only');

    $job = new ExecuteObjectBulkActionJob('change_status', [$selected], ['status' => 'hidden'], $actor->id);
    $job->handle(app(ObjectBulkActionService::class));

    expect(DB::table('objects')->where('id', $selected)->value('status'))->toBe('hidden')
        // Only the id actually listed was touched — proving objectIds is
        // forwarded exactly as given, not "everything the actor can see".
        ->and(DB::table('objects')->where('id', $untouched)->value('status'))->toBe('draft')
        ->and(DB::table('audits')->where('event', 'object_bulk_hidden')->where('auditable_id', $selected)->count())->toBe(1);
});

it('lets a scope-violation exception from the service escape handle() uncaught, and mutates nothing', function (): void {
    $geo = bulkActionJobGeography();
    $inScope = bulkActionJobSeedObject($geo, ['country_id' => $geo['countryMd'], 'territory_id' => $geo['territoryMd']]);
    $outOfScope = bulkActionJobSeedObject($geo, ['country_id' => $geo['countryUa'], 'territory_id' => $geo['territoryUa']]);
    $actor = bulkActionJobActor(['admin_panel_access', 'object.view', 'object.edit'], 'country', $geo['countryMd'], 'bulk_job_scope_violation');

    $job = new ExecuteObjectBulkActionJob('change_status', [$inScope, $outOfScope], ['status' => 'hidden'], $actor->id);

    expect(fn () => $job->handle(app(ObjectBulkActionService::class)))
        ->toThrow(BulkSelectionScopeException::class);

    expect(DB::table('objects')->where('id', $inScope)->value('status'))->toBe('draft')
        ->and(DB::table('audits')->where('event', 'object_bulk_hidden')->count())->toBe(0);
});

// -----------------------------------------------------------------------
// GenerateSitemapsJob
// -----------------------------------------------------------------------

it('declares the shared "default" hourly-sweep queue', function (): void {
    $job = new GenerateSitemapsJob;

    expect($job->queue)->toBe('default');
});

it('delegates to SitemapBuilder::generate(), which writes the sitemap index onto the configured disk', function (): void {
    Storage::fake((string) config('sitemap.disk'));

    (new GenerateSitemapsJob)->handle(new SitemapBuilder);

    expect(Storage::disk((string) config('sitemap.disk'))->exists('sitemaps/sitemap.xml'))->toBeTrue();
});

it('lets an exception from the builder escape handle() uncaught', function (): void {
    // An unconfigured disk name makes Storage::disk() inside generate()
    // throw for real — the falsification this warrants against a `final`
    // collaborator: no try/catch in handle() means the job itself never
    // swallows it.
    config(['sitemap.disk' => 'bulk-action-job-test-missing-disk']);

    expect(fn () => (new GenerateSitemapsJob)->handle(new SitemapBuilder))
        ->toThrow(InvalidArgumentException::class, 'Disk [bulk-action-job-test-missing-disk] does not have a configured driver.');
});
