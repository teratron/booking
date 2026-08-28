<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Staff\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Staff Account Service — Edge Branches
|--------------------------------------------------------------------------
|
| tests/Feature/Admin/StaffAdministrationTest.php exercises this service's
| happy paths through the Filament panel: account creation and deactivation
| guarded by the last-chief-administrator rule. This file calls the service
| directly to reach what the panel surface never triggers: a partial
| contact update, a contact update that touches nothing at all, and the
| idempotent no-op branches of deactivate() and restore().
|
*/

it('saves and journals only the contact fields that actually change', function (): void {
    $staff = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.test']);
    $actor = User::factory()->create();

    app(StaffAccountService::class)->updateContacts(
        $staff,
        ['name' => 'Old Name', 'email' => 'new@example.test'],
        $actor,
    );

    $staff->refresh();
    expect($staff->name)->toBe('Old Name')
        ->and($staff->email)->toBe('new@example.test');

    $audit = DB::table('audits')
        ->where('event', 'staff_contacts_updated')
        ->where('auditable_id', $staff->id)
        ->first();

    expect($audit)->not->toBeNull();

    // Only the field that changed is present — the unchanged 'name' never
    // reaches the journal even though it was part of the incoming payload.
    expect(json_decode((string) $audit->old_values, true))->toBe(['email' => 'old@example.test'])
        ->and(json_decode((string) $audit->new_values, true))->toBe(['email' => 'new@example.test']);
});

it('ignores data keys outside name and email even when a real field changes alongside them', function (): void {
    $staff = User::factory()->create(['name' => 'Original Name']);
    $actor = User::factory()->create();
    $originalPasswordHash = $staff->password;

    app(StaffAccountService::class)->updateContacts(
        $staff,
        ['password' => 'attempted-password-change', 'phone' => '+37360000000', 'name' => 'New Name'],
        $actor,
    );

    $staff->refresh();
    expect($staff->name)->toBe('New Name')
        // array_intersect_key() against ['name', 'email'] strips 'password'
        // and 'phone' before either reaches forceFill() — a caller cannot
        // smuggle an unrelated column through this write path.
        ->and($staff->password)->toBe($originalPasswordHash)
        ->and($staff->phone)->toBeNull();

    $audit = DB::table('audits')
        ->where('event', 'staff_contacts_updated')
        ->where('auditable_id', $staff->id)
        ->first();

    expect(json_decode((string) $audit->new_values, true))->toBe(['name' => 'New Name']);
});

it('writes no journal entry and leaves the record untouched when nothing actually changes', function (): void {
    $staff = User::factory()->create(['name' => 'Same Name', 'email' => 'same@example.test']);
    $actor = User::factory()->create();

    app(StaffAccountService::class)->updateContacts(
        $staff,
        ['name' => 'Same Name', 'email' => 'same@example.test'],
        $actor,
    );

    expect(DB::table('audits')->where('event', 'staff_contacts_updated')->where('auditable_id', $staff->id)->count())
        ->toBe(0);
});

it('treats deactivating an already-deactivated account as a no-op, bypassing the last-holder guard entirely', function (): void {
    Role::findOrCreate('chief_administrator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $staff = User::factory()->create();
    $staff->assignRole('chief_administrator');
    $actor = User::factory()->create();

    // Deactivate for real first, then capture the persisted state — this
    // staff member is now the role's sole active holder, so a second
    // deactivate() call would fail RoleGrantService::guardDeactivation() if
    // that guard ran again. The idempotent early return at the top of
    // deactivate() must skip the guard entirely instead.
    $staff->forceFill(['blocked_at' => now(), 'blocked_by' => $actor->id])->save();
    $persistedBlockedAt = $staff->fresh()->blocked_at;

    app(StaffAccountService::class)->deactivate($staff->fresh(), $actor);

    $staff->refresh();
    expect($staff->blocked_at->equalTo($persistedBlockedAt))->toBeTrue()
        ->and(DB::table('audits')->where('event', 'staff_deactivated')->where('auditable_id', $staff->id)->count())
        ->toBe(0);
});

it('treats restoring an already-active account as a no-op', function (): void {
    $staff = User::factory()->create();
    $actor = User::factory()->create();

    expect($staff->blocked_at)->toBeNull();

    app(StaffAccountService::class)->restore($staff, $actor);

    expect(DB::table('audits')->where('event', 'staff_restored')->where('auditable_id', $staff->id)->count())
        ->toBe(0);
});

it('restores a deactivated account and journals the blocked state it replaced', function (): void {
    $blocker = User::factory()->create();
    $staff = User::factory()->create();
    $staff->forceFill(['blocked_at' => now()->subHour(), 'blocked_by' => $blocker->id])->save();
    $persisted = $staff->fresh();
    $actor = User::factory()->create();

    app(StaffAccountService::class)->restore($persisted, $actor);

    $staff->refresh();
    expect($staff->blocked_at)->toBeNull()
        ->and($staff->blocked_by)->toBeNull();

    $audit = DB::table('audits')
        ->where('event', 'staff_restored')
        ->where('auditable_id', $staff->id)
        ->first();

    expect($audit)->not->toBeNull();

    $oldValues = json_decode((string) $audit->old_values, true);
    $newValues = json_decode((string) $audit->new_values, true);

    expect($newValues)->toBe(['blocked_at' => null, 'blocked_by' => null])
        ->and($oldValues['blocked_by'])->toBe($blocker->id)
        ->and($oldValues['blocked_at'])->not->toBeNull();
});
