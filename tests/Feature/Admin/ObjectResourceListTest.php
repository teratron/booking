<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Filament\Admin\Resources\Objects\Pages\ListObjects;
use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Object List
|--------------------------------------------------------------------------
|
| The panel's busiest screen. Two of its properties are load-bearing and
| neither is obvious from looking at it: the list is narrowed by the viewer's
| grants at the query, and it renders objects whose moderation state keeps
| them off every public page — which is the whole reason staff need it.
|
*/

function objectListFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $countries = [];
    foreach (['MD', 'GE'] as $code) {
        $countries[$code] = DB::table('countries')->insertGetId([
            'code' => $code, 'currency' => 'EUR', 'phone_code' => '+000',
            'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = User::factory()->create(['name' => 'Marina Ionescu']);
    $ids = [];

    foreach ($countries as $code => $countryId) {
        $levelId = DB::table('territory_levels')->insertGetId([
            'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $territoryId = DB::table('territories')->insertGetId([
            'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $ids[$code] = DB::table('objects')->insertGetId([
            'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
            'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
            'status' => 'published', 'moderation_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('object_translations')->insert([
            'object_id' => $ids[$code], 'locale' => 'en', 'name' => "Villa {$code}",
            'slug' => 'villa-'.strtolower($code),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('contact_channel_types')->insertOrIgnore([
            'id' => 1, 'key' => 'phone', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('contact_channels')->insert([
            'object_id' => $ids[$code], 'contact_channel_type_id' => 1,
            'raw_value' => "+37360000{$ids[$code]}",
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return ['countries' => $countries, 'objects' => $ids, 'owner' => $owner];
}

function objectListActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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

    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('renders the list for an administrator holding the view grant', function (): void {
    objectListFixture();
    objectListActor(['admin_panel_access', 'object.view'], 'none', null, 'unrestricted');

    $this->get(ObjectResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Villa MD')
        ->assertSee('Villa GE');
});

it('excludes another country from a country-scoped administrator list', function (): void {
    $fixture = objectListFixture();
    objectListActor(['admin_panel_access', 'object.view'], 'country', $fixture['countries']['MD'], 'moldova_only');

    $this->get(ObjectResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Villa MD')
        ->assertDontSee('Villa GE');
});

it('shows objects awaiting review, which no public query returns', function (): void {
    objectListFixture();
    objectListActor(['object.view'], 'none', null, 'unrestricted');

    expect(ObjectResource::getEloquentQuery()->count())->toBe(2)
        ->and(Object_::query()->count())->toBe(0);
});

it('refuses an out-of-scope record at the policy, not only in the list', function (): void {
    $fixture = objectListFixture();
    $actor = objectListActor(['object.view'], 'country', $fixture['countries']['MD'], 'moldova_only');

    $moldovan = Object_::query()->withUnmoderated()->findOrFail($fixture['objects']['MD']);
    $georgian = Object_::query()->withUnmoderated()->findOrFail($fixture['objects']['GE']);

    // The list already hides the Georgian object; this is the other half —
    // reaching it by identifier rather than by clicking a row.
    expect($actor->can('view', $moldovan))->toBeTrue()
        ->and($actor->can('view', $georgian))->toBeFalse();
});

it('restricts permanent deletion to the chief administrator regardless of the delete grant', function (): void {
    $fixture = objectListFixture();
    $actor = objectListActor(['object.view', 'object.delete'], 'none', null, 'country_administrator');
    $object = Object_::query()->withUnmoderated()->findOrFail($fixture['objects']['MD']);

    expect($actor->can('delete', $object))->toBeTrue()
        ->and($actor->can('forceDelete', $object))->toBeFalse();

    $chief = objectListActor(['object.view', 'object.delete'], 'none', null, 'chief_administrator');
    expect($chief->can('forceDelete', $object))->toBeTrue();
});

it('keeps a filter set on one request applied on the next', function (): void {
    $fixture = objectListFixture();
    objectListActor(['admin_panel_access', 'object.view'], 'none', null, 'unrestricted');

    Livewire\Livewire::test(ListObjects::class)
        ->set('tableFilters.country_id.value', (string) $fixture['countries']['MD'])
        ->assertCanSeeTableRecords(Object_::query()->withUnmoderated()
            ->where('country_id', $fixture['countries']['MD'])->get());

    // A second component instance, as a later request would be: the filter
    // survives because the contract persists it in the session, not because
    // the first instance still holds it.
    Livewire\Livewire::test(ListObjects::class)
        ->assertCanNotSeeTableRecords(Object_::query()->withUnmoderated()
            ->where('country_id', $fixture['countries']['GE'])->get());
});
