<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\TranslationReport;
use App\Models\ObjectType;
use App\Models\User;
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
| Translation Report — Back Office
|--------------------------------------------------------------------------
|
| The report matrix and its drill-down table both read from the same
| completeness definition: a row counts as translated only when it exists
| and is not `needs_review`. Three fixture rows — one fully translated, one
| copied-but-unreviewed, one missing outright — prove the page renders each
| state distinctly rather than collapsing "exists" and "translated" into
| the same thing, and that the copy/publish actions it exposes actually
| move a row from one state to the next.
|
*/

function translationReportLanguages(): void
{
    DB::table('languages')->insert([
        [
            'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
            'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
            'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);
}

/** @param  ?array<string, mixed>  $ruTranslation  null omits the `ru` row entirely */
function translationReportSeedObjectType(string $key, string $enName, ?array $ruTranslation): ObjectType
{
    $type = ObjectType::create(['key' => $key]);

    DB::table('object_type_translations')->insert([
        'object_type_id' => $type->id, 'locale' => 'en', 'name' => $enName,
        'slug' => Str::slug($enName), 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    if ($ruTranslation !== null) {
        DB::table('object_type_translations')->insert(array_merge([
            'object_type_id' => $type->id, 'locale' => 'ru', 'name' => "{$enName} (RU)",
            'slug' => Str::slug("{$enName}-ru"), 'created_at' => now(), 'updated_at' => now(),
        ], $ruTranslation));
    }

    return $type->fresh();
}

function translationReportActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('renders the report for an administrator holding settings.view and refuses one without it', function (): void {
    $permitted = translationReportActor(['admin_panel_access', 'settings.view'], 'translation_report_admin');
    $refused = translationReportActor(['admin_panel_access'], 'translation_report_no_settings');

    $this->actingAs($permitted)->get(TranslationReport::getUrl(panel: 'admin'))->assertSuccessful();
    $this->actingAs($refused)->get(TranslationReport::getUrl(panel: 'admin'))->assertForbidden();
});

it('distinguishes a fully translated row from one only copied and unreviewed, and one missing entirely', function (): void {
    translationReportLanguages();

    $complete = translationReportSeedObjectType('complete-type', 'Complete Type', [
        'needs_review' => false, 'published_at' => now(),
    ]);
    $needsReview = translationReportSeedObjectType('needs-review-type', 'Needs Review Type', [
        'needs_review' => true, 'published_at' => null,
    ]);
    $missing = translationReportSeedObjectType('missing-type', 'Missing Type', null);

    $actor = translationReportActor(['admin_panel_access', 'settings.view'], 'translation_report_admin');

    // The table defers its own loading; changing entityClass/locale rebuilds
    // the query the next time the table itself is asked to load, but an
    // explicit loadTable() is what actually triggers that — a bare assertOk()
    // re-renders the page around a table that has not re-fetched yet.
    $test = Livewire::actingAs($actor)->test(TranslationReport::class)
        ->assertOk()
        ->set('entityClass', ObjectType::class)
        ->set('locale', 'ru')
        ->call('loadTable');

    // A copied-but-unreviewed row and a genuinely absent row both surface in
    // the drill-down table (neither counts as translated), but the fully
    // translated row is filtered out of it entirely.
    $test->assertCanSeeTableRecords(ObjectType::query()->whereIn('id', [$needsReview->id, $missing->id])->get())
        ->assertCanNotSeeTableRecords(ObjectType::query()->whereKey($complete->id)->get())
        ->assertTableColumnStateSet('translation_state', __('panel.translation_report.state.needs_review'), $needsReview)
        ->assertTableColumnStateSet('translation_state', __('panel.translation_report.state.missing'), $missing);

    $summary = collect($test->instance()->summary());
    $ruRow = $summary->first(fn (array $row): bool => $row['entity']->modelClass === ObjectType::class && $row['locale'] === 'ru');

    expect($ruRow)->not->toBeNull()
        ->and($ruRow['total'])->toBe(3)
        ->and($ruRow['translated'])->toBe(1)
        ->and($ruRow['needsReview'])->toBe(1)
        ->and($ruRow['missing'])->toBe(1);

    // 'toContain' matches by equality, not by predicate — a Closure would
    // never equal a discovered TranslatableEntity, so this collapses the
    // predicate to a plain boolean assertion instead of a silently-always-true
    // (or always-false) comparison against the wrong kind of value.
    expect(collect($test->instance()->entities())->contains(
        fn ($entity): bool => $entity->modelClass === ObjectType::class
    ))->toBeTrue();
    expect($test->instance()->selectableLocales())->toBe(['en', 'ru']);
});

it('copies from the primary language through the page action, marking the target needing review, not translated', function (): void {
    translationReportLanguages();
    $missing = translationReportSeedObjectType('missing-type', 'Missing Type', null);
    $actor = translationReportActor(['admin_panel_access', 'settings.view'], 'translation_report_admin');

    // The table defers its own loading — an explicit loadTable() between
    // set() and callTableAction() is what actually rebuilds it against the
    // new locale; the action's own record resolution reads that loaded
    // state, not a fresh query of its own.
    Livewire::actingAs($actor)->test(TranslationReport::class)
        ->set('entityClass', ObjectType::class)
        ->set('locale', 'ru')
        ->call('loadTable')
        ->callTableAction('copy_from_primary', $missing)
        ->assertNotified(__('panel.translation_report.notifications.copied'));

    $translation = DB::table('object_type_translations')
        ->where('object_type_id', $missing->id)->where('locale', 'ru')->first();

    expect($translation)->not->toBeNull()
        ->and($translation->name)->toBe('Missing Type')
        ->and((bool) $translation->needs_review)->toBeTrue()
        ->and($translation->published_at)->toBeNull();

    // The copy did not make it count as translated — it still shows up as
    // needing review, exactly the gap the copy action exists to flag.
    expect(DB::table('object_type_translations')
        ->where('object_type_id', $missing->id)->where('locale', 'ru')
        ->where('needs_review', false)->exists())->toBeFalse();
});

it('publishes a language version through the page action, and hides the action with nothing to publish', function (): void {
    translationReportLanguages();
    $needsReview = translationReportSeedObjectType('needs-review-type', 'Needs Review Type', [
        'needs_review' => true, 'published_at' => null,
    ]);
    $missing = translationReportSeedObjectType('missing-type', 'Missing Type', null);
    $actor = translationReportActor(['admin_panel_access', 'settings.view'], 'translation_report_admin');

    $test = Livewire::actingAs($actor)->test(TranslationReport::class)
        ->set('entityClass', ObjectType::class)
        ->set('locale', 'ru')
        ->call('loadTable');

    // No `ru` translation exists at all for the missing row, so publishing
    // one is not an offered action — only copy-from-primary is.
    $test->assertTableActionHidden('publish', $missing)
        ->assertTableActionVisible('publish', $needsReview)
        ->callTableAction('publish', $needsReview)
        ->assertNotified(__('panel.translation_report.notifications.published'));

    $translation = DB::table('object_type_translations')
        ->where('object_type_id', $needsReview->id)->where('locale', 'ru')->first();

    expect((bool) $translation->needs_review)->toBeFalse()
        ->and($translation->published_at)->not->toBeNull();

    // The English version's own publish state is untouched by publishing Russian.
    $english = DB::table('object_type_translations')
        ->where('object_type_id', $needsReview->id)->where('locale', 'en')->first();
    expect($english->published_at)->not->toBeNull();
});
