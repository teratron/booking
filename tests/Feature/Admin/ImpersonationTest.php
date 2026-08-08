<?php

declare(strict_types=1);

use App\Exceptions\ImpersonationRefusedException;
use App\Models\Object_;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Audit\ImpersonationContext;
use App\Services\Owners\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Support-Mode Impersonation
|--------------------------------------------------------------------------
|
| The single most sensitive capability in this panel: it grants an
| administrator the full authority of another account. Its journal record
| is unconditional, and every mutation made during the impersonated session
| must be attributed to the administrator, never to the owner — getting the
| attribution direction backwards here is worse than not journalling at
| all, because it actively misleads whoever reads it later.
|
*/

function impersonationGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
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

/** @param  array<string, mixed>  $attributes */
function impersonationOwner(array $attributes = []): User
{
    Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $owner = User::factory()->create($attributes);
    $owner->assignRole('object_owner');

    return $owner->fresh();
}

/** @param  list<string>  $permissions */
function impersonationActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor->fresh();
}

/**
 * @param  array<string, mixed>  $geo
 * @param  array<string, mixed>  $overrides
 */
function impersonationSeedObject(array $geo, User $owner, array $overrides = []): int
{
    return DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $owner->id,
        'object_type_id' => $geo['typeId'],
        'territory_id' => $geo['territoryId'],
        'country_id' => $geo['countryId'],
        'status' => 'draft',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

it('authenticates the administrator as the target owner in the cabinet panel', function (): void {
    $owner = impersonationOwner();
    $actor = impersonationActor(['admin_panel_access', 'impersonate'], 'support');

    app(ImpersonationService::class)->enter($owner, $actor);

    /** @var User $current */
    $current = Auth::guard('web')->user();

    expect($current->id)->toBe($owner->id)
        ->and(session(ImpersonationContext::SESSION_KEY))->toBe($actor->id);
});

it('journals a single entry naming both the actor and the target before the session switches', function (): void {
    $owner = impersonationOwner();
    $actor = impersonationActor(['admin_panel_access', 'impersonate'], 'support');

    app(ImpersonationService::class)->enter($owner, $actor);

    $audit = DB::table('audits')
        ->where('event', 'owner_impersonation_started')
        ->where('auditable_id', $owner->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and(json_decode((string) $audit->new_values, true))->toBe([
            'impersonator_id' => $actor->id,
            'target_owner_id' => $owner->id,
        ]);
});

it('attributes every mutation made while impersonating to the administrator, never the owner', function (): void {
    $geo = impersonationGeography();
    $owner = impersonationOwner();
    $actor = impersonationActor(['admin_panel_access', 'impersonate', 'object.edit'], 'support');
    $objectId = impersonationSeedObject($geo, $owner);

    app(ImpersonationService::class)->enter($owner, $actor);

    // Auto-observed audit: owen-it/laravel-auditing's own resolver, a
    // separate code path from AuditJournal below. The package suppresses
    // its automatic audits in a console context by project-wide config
    // (config/audit.php's 'console' => false) — every Pest run is a
    // console context, so this one assertion needs that suppression lifted
    // to observe the write at all, restored immediately after.
    config(['audit.console' => true]);

    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);
    $object->status = 'published';
    $object->save();

    config(['audit.console' => false]);

    $autoAudit = DB::table('audits')
        ->where('event', 'updated')
        ->where('auditable_type', Object_::class)
        ->where('auditable_id', $objectId)
        ->latest('id')
        ->first();

    expect($autoAudit)->not->toBeNull()
        ->and($autoAudit->user_id)->toBe($actor->id)
        ->and($autoAudit->user_id)->not->toBe($owner->id);

    // AuditJournal's own explicit write, relying on its actor fallback.
    app(AuditJournal::class)->record('object_hidden', $object, [], ['status' => 'hidden'], null, ['object']);

    $explicitAudit = DB::table('audits')
        ->where('event', 'object_hidden')
        ->where('auditable_id', $objectId)
        ->first();

    expect($explicitAudit)->not->toBeNull()
        ->and($explicitAudit->user_id)->toBe($actor->id);
});

it('requires the impersonate permission and refuses an actor who lacks it', function (): void {
    $owner = impersonationOwner();
    $actor = impersonationActor(['admin_panel_access'], 'no_impersonate');

    expect(fn () => app(ImpersonationService::class)->enter($owner, $actor))
        ->toThrow(ImpersonationRefusedException::class);

    expect(session(ImpersonationContext::SESSION_KEY))->toBeNull()
        ->and(DB::table('audits')->where('event', 'owner_impersonation_started')->count())->toBe(0);
});

it('refuses impersonation when the actor itself holds the owner role, even if granted the permission directly', function (): void {
    $owner = impersonationOwner();

    Permission::findOrCreate('impersonate', 'web');
    Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $misconfiguredOwner = User::factory()->create();
    $misconfiguredOwner->assignRole('object_owner');
    $misconfiguredOwner->givePermissionTo('impersonate');

    expect(fn () => app(ImpersonationService::class)->enter($owner, $misconfiguredOwner->fresh()))
        ->toThrow(ImpersonationRefusedException::class);

    expect(DB::table('audits')->where('event', 'owner_impersonation_started')->count())->toBe(0);
});

it('refuses impersonation when the target does not hold the owner role', function (): void {
    $actor = impersonationActor(['admin_panel_access', 'impersonate'], 'support_2');
    $notAnOwner = User::factory()->create();

    expect(fn () => app(ImpersonationService::class)->enter($notAnOwner, $actor))
        ->toThrow(ImpersonationRefusedException::class);

    expect(DB::table('audits')->where('event', 'owner_impersonation_started')->count())->toBe(0);
});

it('writes the journal entry as a plain committed statement outside any transaction the session switch could roll back', function (): void {
    // ImpersonationService::enter() calls AuditJournal::record() (which
    // saves the Audit row directly, with no surrounding transaction) and
    // only afterwards touches the session and the auth guard — there is no
    // shared transaction for a later failure to unwind, which is what makes
    // "the journal entry survives a failed switch" true by construction
    // rather than by convention. Proven here by reading the actual source
    // rather than asserting behaviour through an invented failure hook that
    // does not exist in the implementation.
    $source = file_get_contents(app_path('Services/Owners/ImpersonationService.php'));

    expect($source)->not->toBeFalse();

    $journalCallPosition = strpos((string) $source, '$this->journal->record(');
    $sessionPutPosition = strpos((string) $source, 'Session::put(');
    $transactionPosition = strpos((string) $source, 'DB::transaction(');

    expect($journalCallPosition)->not->toBeFalse()
        ->and($sessionPutPosition)->not->toBeFalse()
        ->and($journalCallPosition)->toBeLessThan($sessionPutPosition)
        ->and($transactionPosition)->toBeFalse();
});

it('exits support mode, restoring the administrator and journalling the end', function (): void {
    $owner = impersonationOwner();
    $actor = impersonationActor(['admin_panel_access', 'impersonate'], 'support_3');

    app(ImpersonationService::class)->enter($owner, $actor);
    app(ImpersonationService::class)->exit();

    /** @var User $current */
    $current = Auth::guard('web')->user();

    expect($current->id)->toBe($actor->id)
        ->and(session(ImpersonationContext::SESSION_KEY))->toBeNull()
        ->and(DB::table('audits')->where('event', 'owner_impersonation_ended')->where('auditable_id', $owner->id)->count())->toBe(1);
});

it('treats exiting when not impersonating as a no-op, not an error', function (): void {
    expect(fn () => app(ImpersonationService::class)->exit())->not->toThrow(Throwable::class);
});
