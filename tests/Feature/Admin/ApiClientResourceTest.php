<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\ApiClients\ApiClientResource;
use App\Filament\Admin\Resources\ApiClients\Pages\CreateApiClient;
use App\Filament\Admin\Resources\ApiClients\Pages\EditApiClient;
use App\Filament\Admin\Resources\ApiClients\Pages\ListApiClients;
use App\Models\ApiClient;
use App\Models\User;
use App\Policies\ApiClientPolicy;
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
| API Client Resource
|--------------------------------------------------------------------------
|
| The outward-facing REST API's consumer registry: creation records who set
| the client up, editing and hard-deleting it through the real Filament
| pages, the list surfacing a genuine token count and creator name, and the
| policy boundary a portal-wide registry needs — an unrestricted grant reaches
| it, a country-scoped one does not, and a caller with none of the api.*
| permissions is refused outright.
|
*/

function apiClientResourceActor(
    array $permissions = ['admin_panel_access', 'api.view', 'api.create', 'api.edit', 'api.delete'],
    string $roleKey = 'api_client_admin',
): User {
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
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

// -----------------------------------------------------------------------
// Creation — the resource's own hook stamps the creating administrator,
// the model itself never receives it from the form.
// -----------------------------------------------------------------------

it('creates an API client through the resource and records the creating administrator', function (): void {
    $actor = apiClientResourceActor();

    Livewire::actingAs($actor)
        ->test(CreateApiClient::class)
        ->fillForm([
            'name' => 'Acme Integrations',
            'contact' => 'ops@acme.example.test',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = ApiClient::query()->where('name', 'Acme Integrations')->firstOrFail();

    expect($client->contact)->toBe('ops@acme.example.test')
        ->and($client->is_active)->toBeTrue()
        ->and($client->created_by)->toBe($actor->id);
});

it('refuses to create an API client without a name', function (): void {
    $actor = apiClientResourceActor();

    Livewire::actingAs($actor)
        ->test(CreateApiClient::class)
        ->fillForm([
            'name' => '',
            'contact' => 'missing-name@acme.example.test',
        ])
        ->call('create')
        ->assertHasFormErrors(['name']);

    expect(ApiClient::query()->where('contact', 'missing-name@acme.example.test')->exists())->toBeFalse();
});

// -----------------------------------------------------------------------
// Editing — contact and activation state change, the name and the original
// creator stay untouched.
// -----------------------------------------------------------------------

it('edits an API client\'s contact and deactivates it, preserving its name and original creator', function (): void {
    $creator = User::factory()->create();
    $clientId = DB::table('api_clients')->insertGetId([
        'name' => 'Existing Integration', 'contact' => 'old@acme.example.test',
        'is_active' => true, 'created_by' => $creator->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = apiClientResourceActor();

    Livewire::actingAs($actor)
        ->test(EditApiClient::class, ['record' => $clientId])
        ->fillForm([
            'contact' => 'new@acme.example.test',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $client = ApiClient::query()->findOrFail($clientId);

    expect($client->name)->toBe('Existing Integration')
        ->and($client->contact)->toBe('new@acme.example.test')
        ->and($client->is_active)->toBeFalse()
        ->and($client->created_by)->toBe($creator->id);
});

// -----------------------------------------------------------------------
// Deletion — API clients carry no soft-delete column, so the edit page's
// delete action removes the row outright.
// -----------------------------------------------------------------------

it('deletes an API client through the edit page\'s delete action, removing it outright', function (): void {
    $creator = User::factory()->create();
    $clientId = DB::table('api_clients')->insertGetId([
        'name' => 'Retired Integration', 'contact' => 'retired@acme.example.test',
        'is_active' => true, 'created_by' => $creator->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = apiClientResourceActor();

    Livewire::actingAs($actor)
        ->test(EditApiClient::class, ['record' => $clientId])
        ->callAction('delete');

    expect(ApiClient::query()->find($clientId))->toBeNull()
        ->and(DB::table('api_clients')->where('id', $clientId)->exists())->toBeFalse();
});

// -----------------------------------------------------------------------
// The list — a real token count and the creator's name, not hand-set flags.
// -----------------------------------------------------------------------

it('lists an API client with its real token count and creator name', function (): void {
    $creator = User::factory()->create(['name' => 'Ion Moderator']);
    $clientId = DB::table('api_clients')->insertGetId([
        'name' => 'Metrics Dashboard', 'contact' => 'metrics@acme.example.test',
        'is_active' => true, 'created_by' => $creator->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach (range(1, 3) as $tokenSequence) {
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => ApiClient::class, 'tokenable_id' => $clientId,
            'name' => "token-{$tokenSequence}", 'token' => hash('sha256', (string) Str::uuid()),
            'abilities' => json_encode(['*']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $actor = apiClientResourceActor();
    test()->actingAs($actor);

    $test = Livewire::test(ListApiClients::class);

    /** @var ApiClient $record */
    $record = $test->instance()->getTableRecord((string) $clientId);

    expect($record->name)->toBe('Metrics Dashboard')
        ->and($record->contact)->toBe('metrics@acme.example.test')
        ->and((int) $record->tokens_count)->toBe(3)
        ->and($record->creator->name)->toBe('Ion Moderator');
});

// -----------------------------------------------------------------------
// Policy boundary — portal-wide administration, the same posture the module
// registry takes: reachable by an unrestricted grant, not by a country-scoped
// one, and not at all without the api.* permissions.
// -----------------------------------------------------------------------

it('admits an unrestricted grant and refuses a country-scoped one, since API clients are portal-wide', function (): void {
    $creator = User::factory()->create();
    DB::table('api_clients')->insert([
        'name' => 'Scope Probe', 'contact' => null,
        'is_active' => true, 'created_by' => $creator->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $unrestricted = apiClientResourceActor(['admin_panel_access', 'api.view'], 'unrestricted_api_admin');

    test()->actingAs($unrestricted)
        ->get(ApiClientResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    expect(ApiClientResource::getEloquentQuery()->count())->toBe(1);

    Permission::findOrCreate('admin_panel_access', 'web');
    Permission::findOrCreate('api.view', 'web');
    $scopedRole = Role::findOrCreate('country_scoped_api_admin', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $scopedRole->syncPermissions(['admin_panel_access', 'api.view']);
    $scoped = User::factory()->create();
    $scoped->assignRole($scopedRole);
    DB::table('role_scopes')->insert([
        'user_id' => $scoped->id, 'role_id' => $scopedRole->id,
        'scope_kind' => 'country', 'scope_reference_id' => 1,
        'granted_by' => $scoped->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    test()->actingAs($scoped->fresh());
    expect(ApiClientResource::getEloquentQuery()->count())->toBe(0);
});

it('refuses the registry entirely to a user holding none of the api.* permissions', function (): void {
    $refused = apiClientResourceActor(['admin_panel_access'], 'api_registry_refused');

    test()->actingAs($refused)
        ->get(ApiClientResource::getUrl('index', panel: 'admin'))
        ->assertForbidden();
});

// -----------------------------------------------------------------------
// The policy itself, exercised directly rather than only through the HTTP
// surface above — every action it exposes, permitted and refused.
// -----------------------------------------------------------------------

it('resolves every ApiClientPolicy action from the acting user\'s own api.* grants', function (): void {
    $client = ApiClient::query()->create([
        'name' => 'Policy Probe', 'contact' => null,
        'is_active' => true, 'created_by' => User::factory()->create()->id,
    ]);

    $permitted = apiClientResourceActor(
        ['admin_panel_access', 'api.view', 'api.create', 'api.edit', 'api.delete'],
        'policy_permitted',
    );
    $refused = apiClientResourceActor(['admin_panel_access'], 'policy_refused');

    $policy = app(ApiClientPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $client))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue()
        ->and($policy->update($permitted, $client))->toBeTrue()
        ->and($policy->delete($permitted, $client))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $client))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse()
        ->and($policy->update($refused, $client))->toBeFalse()
        ->and($policy->delete($refused, $client))->toBeFalse();
});
