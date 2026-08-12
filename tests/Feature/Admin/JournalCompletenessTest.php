<?php

declare(strict_types=1);

use App\Filament\Admin\Auth\Login;
use App\Models\Module;
use App\Models\Object_;
use App\Models\User;
use App\Services\Moderation\ModerationDecisionService;
use App\Services\Moderation\ModerationPipeline;
use App\Services\Modules\ModuleAdministrator;
use App\Services\Objects\AvailabilityAdministrationService;
use App\Services\Objects\ObjectBulkActionService;
use App\Services\Objects\ObjectLifecycleService;
use App\Services\Owners\ImpersonationService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Journal Completeness & Append-Only Enforcement
|--------------------------------------------------------------------------
|
| Every event class the specification enumerates must actually reach the
| journal — a class silently uncovered by any write path is a gap nothing
| else in the panel would surface, since the journal itself has no other
| consumer checking its own completeness. The second half proves the
| append-only guarantee is a database-level fact, not an application
| convention: the same role the web application connects as is denied
| UPDATE and DELETE on `audits` directly, by the Phase 1 trigger, before
| any application code gets a chance to refuse anything itself.
|
*/

function journalCompletenessFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function journalCompletenessActor(): User
{
    $permissions = [
        'admin_panel_access', 'object.view', 'object.create', 'object.edit',
        'object.publish', 'object.delete', 'object.export',
        'moderation.view', 'moderation.edit',
        'settings.view', 'settings.edit', 'impersonate',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('journal_completeness_actor', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

/** Exactly one row for $event, with at least one of old/new genuinely populated. */
function assertJournaled(string $label, string $event): void
{
    $rows = DB::table('audits')->where('event', $event)->get();

    expect($rows)->toHaveCount(1, "Event class [{$label}] (`{$event}`) did not produce exactly one journal row.");

    $row = $rows->first();
    $old = json_decode((string) $row->old_values, true) ?? [];
    $new = json_decode((string) $row->new_values, true) ?? [];

    expect($old !== [] || $new !== [])
        ->toBeTrue("Event class [{$label}] (`{$event}`) recorded neither a previous nor a new value.");
}

it('produces exactly one journal row for every event class the specification enumerates', function (): void {
    // Console auditing is off by default (config/audit.php) to keep seeders
    // and artisan commands quiet — a web request is not a console context,
    // so this flips it back on for the duration of the test to prove the
    // automatic half of the journal (object creation and edit) genuinely
    // fires in production, not just the explicit lifecycle-action calls.
    config(['audit.console' => true]);

    $fixture = journalCompletenessFixture();
    $actor = journalCompletenessActor();
    $owner = User::factory()->create();
    $newOwner = User::factory()->create();

    Role::findOrCreate('object_owner', 'web');
    $owner->assignRole('object_owner');

    // 1. sign-in — checked immediately, since impersonation later in this
    // same sequence also switches the authenticated guard via
    // `loginUsingId()`, which dispatches the identical `Login` event and
    // would otherwise leave two `sign_in` rows by the time of a single
    // end-of-test count.
    Livewire::test(Login::class)
        ->fillForm(['email' => $actor->email, 'password' => 'password'])
        ->call('authenticate');

    assertJournaled('sign-in', 'sign_in');

    test()->actingAs($actor->fresh());

    // 2. object creation (the automatic, Auditable-observed half of the
    // journal) — checked immediately, since every lifecycle action from
    // here on also calls `save()` on the same object and would otherwise
    // inflate this count too.
    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->owner_id = $owner->id;
    $object->object_type_id = $fixture['typeId'];
    $object->territory_id = $fixture['territoryId'];
    $object->country_id = $fixture['countryId'];
    $object->status = 'draft';
    $object->moderation_status = 'approved';
    $object->fill(['name' => 'Villa Original', 'slug' => 'villa-original']);
    $object->save();

    assertJournaled('object creation', 'created');

    // 3. object edit (automatic; a plain field change, not a lifecycle
    // action) — checked immediately for the same reason as creation above.
    // A real column on `objects` itself, not a translated attribute: `name`
    // routes through astrotomic to `object_translations`, leaving nothing
    // dirty on the object's own row for Auditable to observe.
    $object->address = 'Strada Independenței 1';
    $object->save();

    assertJournaled('object edit', 'updated');

    // 4. content publication
    app(ObjectLifecycleService::class)->publish($object->fresh(), $actor);

    // 5. availability toggle
    app(AvailabilityAdministrationService::class)->override($object->fresh(), 'unavailable', $actor);

    // 6. owner change
    app(ObjectLifecycleService::class)->transferOwnership($object->fresh(), $newOwner, $actor);

    // 7. moderation decision
    $outcome = app(ModerationPipeline::class)->submit(
        target: $object->fresh(),
        section: 'name',
        previousData: ['name' => 'Villa Renamed'],
        proposedData: ['name' => 'Villa Moderated'],
        submittedBy: $newOwner,
        objectId: $object->id,
        ownerId: $newOwner->id,
        countryId: $fixture['countryId'],
    );
    app(ModerationDecisionService::class)->approve($outcome->request, $actor);

    // 8. data export
    app(ObjectBulkActionService::class)->execute('export', [$object->id], [], $actor);

    // 9. settings change
    app(SettingsRepository::class)->set('moderation.partial_acceptance_enabled', true, $actor);

    // 10. module toggle
    $moduleId = DB::table('modules')->insertGetId([
        'key' => 'journal_completeness_module', 'default_state' => 'disabled', 'scopable_levels' => '[]',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(ModuleAdministrator::class)->setState(Module::query()->findOrFail($moduleId), 'portal', null, true, $actor);

    // 11. deletion & 12. restoration
    app(ObjectLifecycleService::class)->archive($object->fresh(), $actor);
    $trashed = Object_::query()->withUnmoderated()->onlyTrashed()->findOrFail($object->id);
    app(ObjectLifecycleService::class)->restore($trashed, $actor);

    // 13. impersonation
    app(ImpersonationService::class)->enter($owner->fresh(), $actor);

    $classes = [
        'content publication' => 'object_published',
        'availability toggle' => 'availability_overridden',
        'owner change' => 'object_ownership_transferred',
        'moderation decision' => 'moderation_approved',
        'data export' => 'object_bulk_exported',
        'settings change' => 'setting_changed',
        'module toggle' => 'module_toggled',
        'deletion' => 'deleted',
        'restoration' => 'restored',
        'impersonation' => 'owner_impersonation_started',
    ];

    // Asserted against the specification's own enumeration first — an event
    // class quietly dropped from this map (or from the two checked inline
    // above, sign-in and object creation/edit) would silently narrow what
    // the test covers rather than failing loudly the way an unimplemented
    // class should.
    expect(array_merge(['sign-in', 'object creation', 'object edit'], array_keys($classes)))
        ->toEqualCanonicalizing([
            'sign-in', 'object creation', 'object edit', 'owner change',
            'availability toggle', 'content publication', 'moderation decision',
            'data export', 'settings change', 'module toggle',
            'deletion', 'restoration', 'impersonation',
        ]);

    foreach ($classes as $label => $event) {
        assertJournaled($label, $event);
    }
});

it('refuses UPDATE and DELETE against the journal as the application\'s own database role, not application code', function (): void {
    $auditId = DB::table('audits')->insertGetId([
        'event' => 'journal_completeness_probe',
        'auditable_type' => Object_::class,
        'auditable_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Each attempt runs in its own transaction: a trigger-raised error
    // aborts the surrounding transaction, and reusing one across both
    // attempts would leave the second running against an already-failed
    // transaction rather than genuinely exercising the DELETE path.
    expect(fn () => DB::transaction(
        fn () => DB::table('audits')->where('id', $auditId)->update(['event' => 'tampered'])
    ))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(
        fn () => DB::table('audits')->where('id', $auditId)->delete()
    ))->toThrow(QueryException::class);

    // Not a silent no-op — the row is untouched, not merely zero-rows-matched.
    expect(DB::table('audits')->where('id', $auditId)->value('event'))->toBe('journal_completeness_probe');
});

it('skips package changes — Phase 3 advertising has no model yet to exercise it', function (): void {})
    ->skip('Package changes belong to Phase 3 (commerce/advertising); no model exists yet to produce this event.');

it('skips position changes — Phase 3 placement has no model yet to exercise it', function (): void {})
    ->skip('Position changes belong to Phase 3 (placement/ranking); no model exists yet to produce this event.');

it('skips bumps — Phase 3 advertising has no model yet to exercise it', function (): void {})
    ->skip('Bumps belong to Phase 3 (commerce/advertising); no model exists yet to produce this event.');
