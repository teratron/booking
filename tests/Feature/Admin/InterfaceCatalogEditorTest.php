<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\InterfaceCatalogEditor;
use App\Models\InterfaceCatalogOverride;
use App\Models\Language;
use App\Models\User;
use App\Services\Localization\InterfaceCatalog;
use App\Services\Localization\InterfaceCatalogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Interface Catalog Editor
|--------------------------------------------------------------------------
|
| The editor's form is built from `InterfaceCatalog::canonicalKeys()` and
| pre-filled from `InterfaceCatalogRepository::currentValues()`, one Textarea
| per active locale per key, field-named as `{locale}__{group}__{key}` with
| dots encoded as `_dot_`. Saving must decode that back to the exact original
| dot key and persist only what an administrator actually changed — writing
| the full pre-filled state back unfiltered would silently turn every
| untouched, still-shipped key into a stored override on the very first save.
|
| The catalog already spans 1,400+ keys across two active locales, one
| Textarea each inside nested Tabs/Fieldsets — mounting or rendering this
| page builds that whole schema at once and exceeds a stock 128M CLI
| memory_limit, independent of anything an individual test here does (it
| reproduces on a bare `Livewire::test()` mount, not only on a full HTTP
| render). Real deployments run PHP-FPM with more headroom than the CLI
| default; this mirrors that for every test in this file instead of failing
| on a display-hidden tab.
|
*/

// Raised once per test, never restored: mounting this page is exactly what
// pushes usage past the CLI default, so an afterEach() attempting to set
// the limit back down would deterministically fail every time — the
// mount's own peak usage already exceeds the value being restored to,
// which is the same "already past the limit being set" failure this
// project's DemoVolumeSeederTest is separately known to hit. Leaving the
// raised limit in place for the rest of this process costs nothing; it
// permits more, it does not consume anything on its own.
beforeEach(function (): void {
    ini_set('memory_limit', '512M');
});

/** @return array{en: Language, ru: Language, ro: Language} */
function catalogEditorLanguages(): array
{
    $now = now();

    DB::table('languages')->insert([
        ['code' => 'en', 'short_label' => 'EN', 'text_direction' => 'ltr', 'is_active' => true, 'is_primary' => true, 'display_order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['code' => 'ru', 'short_label' => 'RU', 'text_direction' => 'ltr', 'is_active' => true, 'is_primary' => false, 'display_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ['code' => 'ro', 'short_label' => 'RO', 'text_direction' => 'ltr', 'is_active' => false, 'is_primary' => false, 'display_order' => 3, 'created_at' => $now, 'updated_at' => $now],
    ]);

    return [
        'en' => Language::query()->where('code', 'en')->firstOrFail(),
        'ru' => Language::query()->where('code', 'ru')->firstOrFail(),
        'ro' => Language::query()->where('code', 'ro')->firstOrFail(),
    ];
}

function catalogEditorActor(string $roleKey = 'catalog_editor'): User
{
    foreach (['admin_panel_access', 'settings.edit'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['admin_panel_access', 'settings.edit']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('loads shipped defaults and administrator overrides into the form for active locales only', function (): void {
    catalogEditorLanguages();
    $actor = catalogEditorActor();

    InterfaceCatalogOverride::query()->create([
        'locale' => 'en', 'group' => 'panel', 'key' => 'brand', 'value' => 'Custom Admin Portal',
    ]);

    // Read straight from the shipped file, not through __() — the translator
    // caches a loaded group for the rest of the process, which would mask
    // whether the page itself resolved the fallback correctly.
    $shippedRuBrand = app(InterfaceCatalog::class)->shipped('panel', 'ru')['brand'];

    Livewire::actingAs($actor)
        ->test(InterfaceCatalogEditor::class)
        ->assertSchemaStateSet([
            'en__panel__brand' => 'Custom Admin Portal',
            'ru__panel__brand' => $shippedRuBrand,
        ])
        // 'ro' is registered but inactive — the editor must not offer a field
        // for a language nobody can currently see the portal rendered in.
        ->assertFormFieldDoesNotExist('ro__panel__brand');
});

it('persists an edited value, keeps a dotted catalog key intact through the field-name encoding, and journals only the changed group', function (): void {
    $languages = catalogEditorLanguages();
    $actor = catalogEditorActor();

    Livewire::actingAs($actor)
        ->test(InterfaceCatalogEditor::class)
        ->fillForm([
            'en__panel__brand' => 'Riviera Admin',
            // 'interface_catalog.saved' round-trips through fieldName()'s
            // '.' -> '_dot_' encoding and decodeFieldName()'s reverse of it.
            'en__panel__interface_catalog_dot_saved' => 'Catalog changes are live.',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('panel.interface_catalog.saved'));

    $repository = app(InterfaceCatalogRepository::class);

    expect($repository->currentValue('en', 'panel', 'brand'))->toBe('Riviera Admin')
        ->and($repository->currentValue('en', 'panel', 'interface_catalog.saved'))->toBe('Catalog changes are live.');

    $panelEntry = DB::table('audits')
        ->where('event', 'interface_catalog_edited')
        ->where('auditable_id', $languages['en']->id)
        ->where('new_values', 'like', '%"panel"%')
        ->first();

    expect($panelEntry)->not->toBeNull()
        ->and($panelEntry->new_values)->toContain('brand')
        ->and($panelEntry->tags)->toBe('localization');

    // Nothing in the 'public' group or in 'ru' was touched by this submit,
    // so the fix that filters the save down to genuinely changed keys must
    // leave both untouched — no journal entry, no stored override.
    expect(DB::table('audits')
        ->where('event', 'interface_catalog_edited')
        ->where('auditable_id', $languages['en']->id)
        ->where('new_values', 'like', '%"public"%')
        ->exists())->toBeFalse()
        ->and(InterfaceCatalogOverride::query()->where('locale', 'ru')->exists())->toBeFalse();
});

it('clears an override back to the shipped default when a field is submitted blank', function (): void {
    catalogEditorLanguages();
    $actor = catalogEditorActor();

    // Seed directly through the repository so this test's own call('save')
    // on the page exercises only the blank-clears-override branch.
    app(InterfaceCatalogRepository::class)->save('en', 'panel', ['brand' => 'Temporary Override'], $actor);

    expect(InterfaceCatalogOverride::query()->where('locale', 'en')->where('group', 'panel')->where('key', 'brand')->exists())->toBeTrue();

    Livewire::actingAs($actor)
        ->test(InterfaceCatalogEditor::class)
        ->fillForm(['en__panel__brand' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(InterfaceCatalogOverride::query()->where('locale', 'en')->where('group', 'panel')->where('key', 'brand')->exists())->toBeFalse()
        ->and(app(InterfaceCatalogRepository::class)->currentValue('en', 'panel', 'brand'))->toBe('Portal Administration');
});

it('renders for an administrator holding settings.edit and refuses one without it', function (): void {
    catalogEditorLanguages();
    $permitted = catalogEditorActor('catalog_editor_permitted');

    foreach (['admin_panel_access'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $refusedRole = Role::findOrCreate('catalog_editor_refused', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $refusedRole->syncPermissions(['admin_panel_access']);
    $refused = User::factory()->create();
    $refused->assignRole($refusedRole);

    test()->actingAs($permitted)->get(InterfaceCatalogEditor::getUrl(panel: 'admin'))->assertSuccessful();
    test()->actingAs($refused->fresh())->get(InterfaceCatalogEditor::getUrl(panel: 'admin'))->assertForbidden();
});
