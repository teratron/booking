<?php

declare(strict_types=1);

use App\Exceptions\LanguageAdministrationRefusedException;
use App\Filament\Admin\Resources\Languages\LanguageResource;
use App\Filament\Admin\Resources\Languages\Pages\ListLanguages;
use App\Filament\Admin\Resources\Languages\Tables\LanguagesTable;
use App\Models\InterfaceCatalogOverride;
use App\Models\Language;
use App\Models\User;
use App\Services\Localization\InterfaceCatalog;
use App\Services\Localization\InterfaceCatalogRepository;
use App\Services\Localization\LanguageAdministrationService;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\Filament\TableHost;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Language Administration
|--------------------------------------------------------------------------
|
| The language registry is data, not code: toggling
| a language, setting the primary, and reordering the switcher are all
| administrator actions with no deployment involved. A database-backed
| loader overlays the file-shipped interface catalogs, the database winning,
| and every lookup falls back to the current primary language — never a raw
| key — including for a language activated with no catalog file of its own.
|
*/

/** @return array{en: Language, ru: Language, ro: Language} */
function languageFixtures(): array
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

function languageActor(): User
{
    foreach (['admin_panel_access', 'system.view', 'system.edit'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('language_admin', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['admin_panel_access', 'system.view', 'system.edit']);

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

it('lets an administrator activate an inactive language', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    app(LanguageAdministrationService::class)->activate($languages['ro'], $actor);

    expect($languages['ro']->fresh()->is_active)->toBeTrue();
});

it('refuses to deactivate the primary language', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    expect(fn () => app(LanguageAdministrationService::class)->deactivate($languages['en'], $actor))
        ->toThrow(LanguageAdministrationRefusedException::class);

    expect($languages['en']->fresh()->is_active)->toBeTrue();
});

it('refuses to deactivate the last active language', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    // Make ru primary so en is free to deactivate, then deactivate en,
    // leaving ru as the sole active language — the next deactivate must refuse.
    // Re-fetched after the primary swap: the raw query-builder update behind
    // makePrimary() does not refresh an already-loaded model instance.
    app(LanguageAdministrationService::class)->makePrimary($languages['ru'], $actor);
    app(LanguageAdministrationService::class)->deactivate($languages['en']->fresh(), $actor);

    expect(fn () => app(LanguageAdministrationService::class)->deactivate($languages['ru'], $actor))
        ->toThrow(LanguageAdministrationRefusedException::class);

    expect($languages['ru']->fresh()->is_active)->toBeTrue();
});

it('deactivates a non-primary active language without touching its translation rows', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    app(InterfaceCatalogRepository::class)->save('ru', 'panel', ['brand' => 'Портал'], $actor);

    app(LanguageAdministrationService::class)->deactivate($languages['ru'], $actor);

    expect($languages['ru']->fresh()->is_active)->toBeFalse()
        ->and(InterfaceCatalogOverride::query()->where('locale', 'ru')->where('key', 'brand')->value('value'))->toBe('Портал');

    // Reactivation is lossless — the override survives the toggle untouched.
    app(LanguageAdministrationService::class)->activate($languages['ru'], $actor);

    expect(app(InterfaceCatalogRepository::class)->currentValue('ru', 'panel', 'brand'))->toBe('Портал');
});

it('sets exactly one primary language, unsetting the previous one', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    app(LanguageAdministrationService::class)->makePrimary($languages['ru'], $actor);

    expect($languages['ru']->fresh()->is_primary)->toBeTrue()
        ->and($languages['en']->fresh()->is_primary)->toBeFalse()
        ->and(Language::query()->where('is_primary', true)->count())->toBe(1);
});

it('refuses to make an inactive language primary', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    expect(fn () => app(LanguageAdministrationService::class)->makePrimary($languages['ro'], $actor))
        ->toThrow(LanguageAdministrationRefusedException::class);

    expect($languages['ro']->fresh()->is_primary)->toBeFalse();
});

it('wires the language list as reorderable on display_order and journals a completed reorder', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    $table = LanguagesTable::configure(Table::make(new TableHost));

    expect($table->getReorderColumn())->toBe('display_order');

    app(LanguageAdministrationService::class)->journalReorder(
        [$languages['ru']->id, $languages['en']->id],
        $actor,
    );

    expect(DB::table('audits')
        ->where('event', 'language_reordered')
        ->where('auditable_id', $languages['ru']->id)
        ->exists())->toBeTrue();
});

it('renders a catalog entry edited through the repository on the very next lookup', function (): void {
    languageFixtures();
    $actor = languageActor();

    // Read the shipped default straight from the file, not through __() —
    // the translator caches a loaded group for the rest of the process, so
    // calling __() before the override exists would prime that cache and
    // mask the very behaviour this test proves.
    expect(app(InterfaceCatalog::class)->shipped('panel', 'en')['brand'])->toBe('Portal Administration');

    app(InterfaceCatalogRepository::class)->save('en', 'panel', ['brand' => 'Custom Portal Name'], $actor);

    expect(__('panel.brand', [], 'en'))->toBe('Custom Portal Name');
});

it('reverts to the shipped default when an override is cleared back to blank', function (): void {
    languageFixtures();
    $actor = languageActor();

    app(InterfaceCatalogRepository::class)->save('en', 'panel', ['brand' => 'Custom Portal Name'], $actor);
    expect(InterfaceCatalogOverride::query()->where('locale', 'en')->where('key', 'brand')->exists())->toBeTrue();

    app(InterfaceCatalogRepository::class)->save('en', 'panel', ['brand' => ''], $actor);

    expect(InterfaceCatalogOverride::query()->where('locale', 'en')->where('key', 'brand')->exists())->toBeFalse()
        ->and(app(InterfaceCatalogRepository::class)->currentValue('en', 'panel', 'brand'))->toBe('Portal Administration');
});

it('falls back to the primary language for a key the requested locale has no catalog file for, rather than rendering a raw key', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    // Romanian ships with no resources/lang/ro directory at all.
    app(LanguageAdministrationService::class)->activate($languages['ro'], $actor);

    expect(__('panel.brand', [], 'ro'))->toBe('Portal Administration')
        ->and(__('panel.brand', [], 'ro'))->not->toBe('panel.brand');
});

it('makes a freshly activated language with no catalog file usable immediately, resolving every key through fallback', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    app(LanguageAdministrationService::class)->activate($languages['ro'], $actor);

    expect(__('panel.brand', [], 'ro'))->toBe('Portal Administration')
        ->and(__('panel.navigation.geography', [], 'ro'))->toBe('Geography')
        ->and(__('panel.objects.columns.name', [], 'ro'))->toBe('Name');
});

/*
|--------------------------------------------------------------------------
| LanguageResource — Labels and Configured Table
|--------------------------------------------------------------------------
|
| Every other test above drives the administration service directly, so the
| resource's own label methods and its wiring of LanguagesTable are never
| actually reached until the index page is rendered through the panel.
|
*/

it('renders the language list through the resource, using its own model labels and configured table', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    $this->actingAs($actor)
        ->get(LanguageResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful()
        ->assertSee(__('panel.languages.title'))
        ->assertSee($languages['en']->code)
        ->assertSee($languages['ru']->code);
});

/*
|--------------------------------------------------------------------------
| LanguagesTable — Record Actions
|--------------------------------------------------------------------------
|
| The service-level tests above exercise LanguageAdministrationService in
| isolation; these drive the same operations through the table's own
| record actions, which is where the success/refusal notification wiring
| actually lives.
|
*/

it('activates a language through the table action and notifies success', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    Livewire::actingAs($actor)
        ->test(ListLanguages::class)
        ->callTableAction('activate', $languages['ro'])
        ->assertNotified(__('panel.languages.notifications.activated'));

    expect($languages['ro']->fresh()->is_active)->toBeTrue();
});

it('refuses deactivating the primary language at the service level, independent of the action button that hides it', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    // The deactivate button is never shown for the primary language (see
    // its `visible()` closure) — Filament enforces visible() server-side for
    // its own mountTableAction()/callMountedAction() test helpers too, so
    // there is no way to drive the hidden action itself through the panel to
    // prove a second, independent refusal exists. LanguageAdministrationService
    // ::deactivate() carries that refusal directly, which is what actually
    // matters: hiding a button is a usability affordance, never the access
    // control, so the guarantee has to hold here regardless of the UI.
    expect(fn () => app(LanguageAdministrationService::class)->deactivate($languages['en'], $actor))
        ->toThrow(LanguageAdministrationRefusedException::class);

    expect($languages['en']->fresh()->is_active)->toBeTrue();
});

it('deactivates a non-primary language through the table action and notifies success', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    Livewire::actingAs($actor)
        ->test(ListLanguages::class)
        ->callTableAction('deactivate', $languages['ru'])
        ->assertNotified(__('panel.languages.notifications.deactivated'));

    expect($languages['ru']->fresh()->is_active)->toBeFalse();
});

it('refuses making an inactive language primary at the service level, independent of the action button that hides it', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    // Same reasoning as the deactivate-primary case above: the make-primary
    // button is never shown for an inactive language, and Filament's own
    // visible() enforcement makes the hidden action unreachable through the
    // panel's own testing helpers — the guarantee that matters lives in
    // LanguageAdministrationService::makePrimary() itself.
    expect(fn () => app(LanguageAdministrationService::class)->makePrimary($languages['ro'], $actor))
        ->toThrow(LanguageAdministrationRefusedException::class);

    expect($languages['ro']->fresh()->is_primary)->toBeFalse();
});

it('makes a language primary through the table action and notifies success', function (): void {
    $languages = languageFixtures();
    $actor = languageActor();

    Livewire::actingAs($actor)
        ->test(ListLanguages::class)
        ->callTableAction('make_primary', $languages['ru'])
        ->assertNotified(__('panel.languages.notifications.primary_changed'));

    expect($languages['ru']->fresh()->is_primary)->toBeTrue()
        ->and($languages['en']->fresh()->is_primary)->toBeFalse();
});
