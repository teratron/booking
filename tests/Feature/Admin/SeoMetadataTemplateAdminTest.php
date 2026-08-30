<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\SeoMetadataTemplates\Pages\CreateSeoMetadataTemplate;
use App\Filament\Admin\Resources\SeoMetadataTemplates\Pages\EditSeoMetadataTemplate;
use App\Filament\Admin\Resources\SeoMetadataTemplates\Pages\ListSeoMetadataTemplates;
use App\Filament\Admin\Resources\SeoMetadataTemplates\SeoMetadataTemplateResource;
use App\Models\SeoMetadataTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| SEO Metadata Template Administration
|--------------------------------------------------------------------------
|
| The template screen's own create/edit/delete pages each carry a cache
| side effect no test previously exercised: every write must drop
| MetadataResolver's rememberForever cache for the affected entity-type
| and locale pair, or a change made here would sit invisible behind a
| stale cache entry until it happened to expire on its own — and this
| cache is remembered indefinitely, so it never would.
|
*/

function seoTemplateAdminLanguages(): void
{
    DB::table('languages')->insert([
        ['code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);
}

function seoTemplateAdminActor(): User
{
    $permissions = ['admin_panel_access', 'seo.view', 'seo.edit'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('seo_template_admin', 'web');
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

function seoTemplateAdminViewerActor(): User
{
    $permissions = ['admin_panel_access', 'seo.view'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('seo_template_viewer', 'web');
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

it('lists templates and filters the table by entity type', function (): void {
    seoTemplateAdminLanguages();
    $actor = seoTemplateAdminActor();

    $territoryTemplate = SeoMetadataTemplate::create([
        'entity_type' => 'territory', 'locale' => 'en', 'field' => 'title', 'template' => '{name} — Travel Guide',
    ]);
    $articleTemplate = SeoMetadataTemplate::create([
        'entity_type' => 'article', 'locale' => 'en', 'field' => 'title', 'template' => '{name} — Article',
    ]);

    Livewire::actingAs($actor)
        ->test(ListSeoMetadataTemplates::class)
        ->assertCanSeeTableRecords([$territoryTemplate, $articleTemplate])
        ->filterTable('entity_type', 'territory')
        ->assertCanSeeTableRecords([$territoryTemplate])
        ->assertCanNotSeeTableRecords([$articleTemplate]);
});

it('creates a template and drops the resolver cache for its entity type and locale', function (): void {
    seoTemplateAdminLanguages();
    $actor = seoTemplateAdminActor();

    Cache::put('seo_metadata_templates:territory|en', ['title' => 'stale'], now()->addDay());

    Livewire::actingAs($actor)
        ->test(CreateSeoMetadataTemplate::class)
        ->fillForm([
            'entity_type' => 'territory',
            'locale' => 'en',
            'field' => 'title',
            'template' => '{name} in {territory}',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SeoMetadataTemplate::query()->where('entity_type', 'territory')->where('locale', 'en')->where('field', 'title')->exists())->toBeTrue()
        ->and(Cache::has('seo_metadata_templates:territory|en'))->toBeFalse();
});

it('refuses a second template for the same entity type, locale, and field', function (): void {
    seoTemplateAdminLanguages();
    $actor = seoTemplateAdminActor();

    SeoMetadataTemplate::create(['entity_type' => 'territory', 'locale' => 'en', 'field' => 'title', 'template' => 'Existing']);

    Livewire::actingAs($actor)
        ->test(CreateSeoMetadataTemplate::class)
        ->fillForm(['entity_type' => 'territory', 'locale' => 'en', 'field' => 'title', 'template' => 'Duplicate'])
        ->call('create')
        ->assertHasFormErrors(['entity_type']);

    expect(SeoMetadataTemplate::query()->where('entity_type', 'territory')->where('locale', 'en')->where('field', 'title')->count())->toBe(1);
});

it('drops both the old and new cache keys when an edit moves a template to a different entity type and locale', function (): void {
    seoTemplateAdminLanguages();
    $actor = seoTemplateAdminActor();

    $template = SeoMetadataTemplate::create([
        'entity_type' => 'territory', 'locale' => 'en', 'field' => 'title', 'template' => '{name}',
    ]);

    Cache::put('seo_metadata_templates:territory|en', ['title' => 'old-cached'], now()->addDay());
    Cache::put('seo_metadata_templates:article|ru', ['title' => 'new-cached'], now()->addDay());

    Livewire::actingAs($actor)
        ->test(EditSeoMetadataTemplate::class, ['record' => $template->id])
        ->fillForm(['entity_type' => 'article', 'locale' => 'ru', 'field' => 'title', 'template' => '{name}'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Cache::has('seo_metadata_templates:territory|en'))->toBeFalse()
        ->and(Cache::has('seo_metadata_templates:article|ru'))->toBeFalse();
});

it('drops the resolver cache for a template deleted from the edit page', function (): void {
    seoTemplateAdminLanguages();
    $actor = seoTemplateAdminActor();

    $template = SeoMetadataTemplate::create([
        'entity_type' => 'territory', 'locale' => 'en', 'field' => 'title', 'template' => '{name}',
    ]);

    Cache::put('seo_metadata_templates:territory|en', ['title' => '{name}'], now()->addDay());

    Livewire::actingAs($actor)
        ->test(EditSeoMetadataTemplate::class, ['record' => $template->id])
        ->callAction('delete');

    expect(SeoMetadataTemplate::query()->whereKey($template->id)->exists())->toBeFalse()
        ->and(Cache::has('seo_metadata_templates:territory|en'))->toBeFalse();
});

it('refuses a viewer-only grant access to the create and edit pages', function (): void {
    seoTemplateAdminLanguages();
    $viewer = seoTemplateAdminViewerActor();

    $template = SeoMetadataTemplate::create([
        'entity_type' => 'territory', 'locale' => 'en', 'field' => 'title', 'template' => '{name}',
    ]);

    $this->actingAs($viewer)->get(SeoMetadataTemplateResource::getUrl('create', panel: 'admin'))->assertForbidden();
    $this->actingAs($viewer)->get(SeoMetadataTemplateResource::getUrl('edit', ['record' => $template->id], panel: 'admin'))->assertForbidden();
});
