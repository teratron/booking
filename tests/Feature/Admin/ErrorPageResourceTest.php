<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\ErrorPages\ErrorPageResource;
use App\Filament\Admin\Resources\ErrorPages\Pages\CreateErrorPage;
use App\Filament\Admin\Resources\ErrorPages\Pages\EditErrorPage;
use App\Filament\Admin\Resources\ErrorPages\Tables\ErrorPagesTable;
use App\Models\ErrorPage;
use App\Models\User;
use App\Services\Seo\ErrorPageResolver;
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
| Error Page Resource
|--------------------------------------------------------------------------
|
| Full CRUD for the administrator-editable 404 copy through the real
| Filament resource, plus the policy that gates it: viewing is granted by
| the SEO view permission, every write by the SEO edit permission, and only
| an unrestricted grant reaches the registry at all since it is portal-wide
| — the same posture the redirect table takes. The create and edit pages
| also drop the resolver's cache, so that is asserted here rather than
| assumed.
|
*/

function errorPageResourceActor(
    array $permissions = ['admin_panel_access', 'seo.view', 'seo.edit'],
    string $scopeKind = 'none',
    ?int $scopeReferenceId = null,
): User {
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('error_page_admin_'.$scopeKind.'_'.implode('_', $permissions), 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => $scopeKind, 'scope_reference_id' => $scopeReferenceId,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

function errorPageResourceLanguages(): void
{
    DB::table('languages')->insert([
        ['code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);
}

it('creates an error page with per-language translations through the resource, and busts an already-cached miss so the override is resolvable immediately', function (): void {
    errorPageResourceLanguages();
    $actor = errorPageResourceActor();

    // Prime the resolver's cache with the "no override yet" miss, the same
    // way a public 404 response would before any administrator has ever
    // touched this status code — this is what proves the create page's own
    // cache-forgetting matters, rather than the assertion below merely
    // being the very first resolve() call ever made.
    expect(app(ErrorPageResolver::class)->resolve(404, 'en'))->toBeNull();

    Livewire::actingAs($actor)
        ->test(CreateErrorPage::class)
        ->fillForm([
            'status_code' => 404,
            'translations' => [
                'en' => ['title' => 'Page not found', 'body' => 'The page you requested could not be found.'],
                'ru' => ['title' => 'Страница не найдена', 'body' => 'Запрошенная страница не найдена.'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $errorPage = ErrorPage::query()->where('status_code', 404)->firstOrFail();

    expect(DB::table('error_page_translations')->where('error_page_id', $errorPage->id)->where('locale', 'en')->value('title'))->toBe('Page not found')
        ->and(DB::table('error_page_translations')->where('error_page_id', $errorPage->id)->where('locale', 'ru')->value('title'))->toBe('Страница не найдена');

    // Had the pre-primed miss survived, resolve() would still return null
    // here despite the row now existing — it does not, which is only true
    // because the create page forgot the cache for every language it wrote.
    expect(app(ErrorPageResolver::class)->resolve(404, 'en'))
        ->toBe(['title' => 'Page not found', 'body' => 'The page you requested could not be found.']);
});

it('omits a locale left entirely blank rather than writing an empty translation row', function (): void {
    errorPageResourceLanguages();
    $actor = errorPageResourceActor();

    Livewire::actingAs($actor)
        ->test(CreateErrorPage::class)
        ->fillForm([
            'status_code' => 404,
            'translations' => [
                'en' => ['title' => 'Page not found', 'body' => 'The page you requested could not be found.'],
                'ru' => ['title' => '', 'body' => ''],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $errorPage = ErrorPage::query()->where('status_code', 404)->firstOrFail();

    expect(DB::table('error_page_translations')->where('error_page_id', $errorPage->id)->where('locale', 'en')->exists())->toBeTrue()
        ->and(DB::table('error_page_translations')->where('error_page_id', $errorPage->id)->where('locale', 'ru')->exists())->toBeFalse();
});

it('refuses to save a second error page for a status code already in use', function (): void {
    errorPageResourceLanguages();
    DB::table('error_pages')->insert(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);
    $actor = errorPageResourceActor();

    Livewire::actingAs($actor)
        ->test(CreateErrorPage::class)
        ->fillForm([
            'status_code' => 404,
            'translations' => ['en' => ['title' => 'Duplicate', 'body' => 'Duplicate body.']],
        ])
        ->call('create')
        ->assertHasFormErrors(['status_code']);

    expect(ErrorPage::query()->where('status_code', 404)->count())->toBe(1);
});

it('edits an error page\'s translations through the resource and refreshes the cached override for the language touched', function (): void {
    errorPageResourceLanguages();

    $errorPageId = DB::table('error_pages')->insertGetId(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('error_page_translations')->insert([
        ['error_page_id' => $errorPageId, 'locale' => 'en', 'title' => 'Original title', 'body' => 'Original body.', 'created_at' => now(), 'updated_at' => now()],
        ['error_page_id' => $errorPageId, 'locale' => 'ru', 'title' => 'Исходный заголовок', 'body' => 'Исходное тело.', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Prime the resolver's cache with the pre-edit value, so the assertion
    // below actually proves the edit page forgets it rather than the
    // resolver simply never having been asked yet.
    expect(app(ErrorPageResolver::class)->resolve(404, 'en'))->toBe(['title' => 'Original title', 'body' => 'Original body.']);

    $actor = errorPageResourceActor();

    Livewire::actingAs($actor)
        ->test(EditErrorPage::class, ['record' => $errorPageId])
        ->fillForm([
            'translations' => ['en' => ['title' => 'Updated title', 'body' => 'Updated body.']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('error_page_translations')->where('error_page_id', $errorPageId)->where('locale', 'en')->value('title'))->toBe('Updated title')
        ->and(app(ErrorPageResolver::class)->resolve(404, 'en'))->toBe(['title' => 'Updated title', 'body' => 'Updated body.'])
        // The Russian translation was never part of this submission, so the
        // reconciliation loop leaves its row untouched.
        ->and(DB::table('error_page_translations')->where('error_page_id', $errorPageId)->where('locale', 'ru')->value('title'))->toBe('Исходный заголовок');
});

it('lists error pages through the index page', function (): void {
    errorPageResourceLanguages();
    DB::table('error_pages')->insert(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);
    $actor = errorPageResourceActor();

    test()->actingAs($actor)
        ->get(ErrorPageResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();
});

it('deletes an error page through its edit page delete action and drops the cached override for every language it held', function (): void {
    errorPageResourceLanguages();

    $errorPageId = DB::table('error_pages')->insertGetId(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('error_page_translations')->insert([
        ['error_page_id' => $errorPageId, 'locale' => 'en', 'title' => 'Title', 'body' => 'Body.', 'created_at' => now(), 'updated_at' => now()],
        ['error_page_id' => $errorPageId, 'locale' => 'ru', 'title' => 'Заголовок', 'body' => 'Тело.', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $resolver = app(ErrorPageResolver::class);
    expect($resolver->resolve(404, 'en'))->not->toBeNull()
        ->and($resolver->resolve(404, 'ru'))->not->toBeNull();

    $actor = errorPageResourceActor();

    Livewire::actingAs($actor)
        ->test(EditErrorPage::class, ['record' => $errorPageId])
        ->callAction('delete');

    expect(ErrorPage::query()->find($errorPageId))->toBeNull();

    // Had the cache survived the delete, resolve() would still return the
    // stale cached title/body despite the row being gone — it does not,
    // which is only true because the delete action's `after()` hook forgot
    // both languages' cache entries.
    expect($resolver->resolve(404, 'en'))->toBeNull()
        ->and($resolver->resolve(404, 'ru'))->toBeNull();
});

it('sorts the error page list by status code by default', function (): void {
    $table = ErrorPagesTable::configure(Table::make(new TableHost));

    expect($table->getDefaultSortColumn())->toBe('status_code')
        ->and($table->getDefaultSortDirection())->toBe('asc');
});

it('grants every error page ability to an unrestricted grant holding both SEO permissions, and refuses a country-scoped one', function (): void {
    errorPageResourceLanguages();
    $errorPageId = DB::table('error_pages')->insertGetId(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);
    $errorPage = ErrorPage::query()->findOrFail($errorPageId);

    $unrestricted = errorPageResourceActor();

    expect($unrestricted->can('viewAny', ErrorPage::class))->toBeTrue()
        ->and($unrestricted->can('view', $errorPage))->toBeTrue()
        ->and($unrestricted->can('create', ErrorPage::class))->toBeTrue()
        ->and($unrestricted->can('update', $errorPage))->toBeTrue()
        ->and($unrestricted->can('delete', $errorPage))->toBeTrue();

    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => DB::table('languages')->where('code', 'en')->value('id'),
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryScoped = errorPageResourceActor(scopeKind: 'country', scopeReferenceId: $countryId);

    // Error page copy is portal-wide, not scoped to a country, territory, or
    // category — the same reasoning the redirect table follows — so a
    // grant bounded to a country reaches none of it, even holding both
    // permissions the unrestricted actor above holds.
    expect($countryScoped->can('viewAny', ErrorPage::class))->toBeFalse()
        ->and($countryScoped->can('view', $errorPage))->toBeFalse()
        ->and($countryScoped->can('create', ErrorPage::class))->toBeFalse()
        ->and($countryScoped->can('update', $errorPage))->toBeFalse()
        ->and($countryScoped->can('delete', $errorPage))->toBeFalse();
});

it('grants view abilities on the SEO view permission alone, and refuses every write ability without the SEO edit permission', function (): void {
    $errorPageId = DB::table('error_pages')->insertGetId(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);
    $errorPage = ErrorPage::query()->findOrFail($errorPageId);

    $viewOnly = errorPageResourceActor(['admin_panel_access', 'seo.view']);

    expect($viewOnly->can('viewAny', ErrorPage::class))->toBeTrue()
        ->and($viewOnly->can('view', $errorPage))->toBeTrue()
        ->and($viewOnly->can('create', ErrorPage::class))->toBeFalse()
        ->and($viewOnly->can('update', $errorPage))->toBeFalse()
        ->and($viewOnly->can('delete', $errorPage))->toBeFalse();
});

it('refuses every error page ability to a role holding no SEO permission at all', function (): void {
    $errorPageId = DB::table('error_pages')->insertGetId(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);
    $errorPage = ErrorPage::query()->findOrFail($errorPageId);

    $stranger = errorPageResourceActor(['admin_panel_access']);

    expect($stranger->can('viewAny', ErrorPage::class))->toBeFalse()
        ->and($stranger->can('view', $errorPage))->toBeFalse()
        ->and($stranger->can('create', ErrorPage::class))->toBeFalse()
        ->and($stranger->can('update', $errorPage))->toBeFalse()
        ->and($stranger->can('delete', $errorPage))->toBeFalse();
});

it('admits an unrestricted grant and refuses a country-scoped one to the error page registry query, since it is portal-wide', function (): void {
    errorPageResourceLanguages();
    DB::table('error_pages')->insert(['status_code' => 404, 'created_at' => now(), 'updated_at' => now()]);

    $countryId = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => DB::table('languages')->where('code', 'en')->value('id'),
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $unrestricted = errorPageResourceActor();
    test()->actingAs($unrestricted);
    expect(ErrorPageResource::getEloquentQuery()->count())->toBe(1);

    $scoped = errorPageResourceActor(scopeKind: 'country', scopeReferenceId: $countryId);
    test()->actingAs($scoped);
    expect(ErrorPageResource::getEloquentQuery()->count())->toBe(0);
});
