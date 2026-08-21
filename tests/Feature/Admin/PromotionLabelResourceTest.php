<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\PromotionLabels\Pages\CreatePromotionLabel;
use App\Filament\Admin\Resources\PromotionLabels\Pages\EditPromotionLabel;
use App\Filament\Admin\Resources\PromotionLabels\PromotionLabelResource;
use App\Models\PromotionLabel;
use App\Models\User;
use App\Support\Advertising\CardPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Promotion Label Resource
|--------------------------------------------------------------------------
|
| Full CRUD through the real Filament resource for the administrator-defined
| card decoration registry, plus the policy that gates it: an unrestricted
| grant is the only grant that reaches this resource, since a label belongs
| to no single country, territory, or object category.
|
*/

function promotionLabelActor(
    array $permissions = ['admin_panel_access', 'advertising.view', 'advertising.create', 'advertising.edit', 'advertising.delete'],
    string $scopeKind = 'none',
    ?int $scopeReferenceId = null,
): User {
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('promotion_label_admin_'.$scopeKind, 'web');
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

function promotionLabelLanguage(): void
{
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'text_direction' => 'ltr',
        'is_active' => true, 'is_primary' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('creates a promotion label with its colours, position, and a translation, through the resource', function (): void {
    promotionLabelLanguage();
    $actor = promotionLabelActor();

    Livewire::actingAs($actor)
        ->test(CreatePromotionLabel::class)
        ->fillForm([
            'border_colour' => '#111111',
            'text_colour' => '#222222',
            'background_colour' => '#333333',
            'icon' => '★',
            'position_on_card' => CardPosition::TopLeft->value,
            'is_active' => true,
            'translations' => ['en' => ['text' => 'VIP']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $label = PromotionLabel::query()->where('border_colour', '#111111')->firstOrFail();

    expect($label->text_colour)->toBe('#222222')
        ->and($label->background_colour)->toBe('#333333')
        ->and($label->icon)->toBe('★')
        ->and($label->position_on_card)->toBe(CardPosition::TopLeft)
        ->and($label->is_active)->toBeTrue()
        ->and(DB::table('promotion_label_translations')->where('promotion_label_id', $label->id)->where('locale', 'en')->value('text'))->toBe('VIP');
});

it('omits a blank translation rather than persisting an empty row', function (): void {
    promotionLabelLanguage();
    $actor = promotionLabelActor();

    Livewire::actingAs($actor)
        ->test(CreatePromotionLabel::class)
        ->fillForm([
            'border_colour' => '#444444',
            'text_colour' => '#555555',
            'background_colour' => '#666666',
            'position_on_card' => CardPosition::BottomRight->value,
            'is_active' => true,
            'translations' => ['en' => ['text' => '']],
        ])
        ->call('create')
        ->assertHasFormErrors(['translations.en.text']);

    expect(PromotionLabel::query()->where('border_colour', '#444444')->exists())->toBeFalse();
});

it('edits a promotion label\'s colours and translation text, and deactivates it', function (): void {
    promotionLabelLanguage();

    $labelId = DB::table('promotion_labels')->insertGetId([
        'border_colour' => '#000000', 'text_colour' => '#ffffff', 'background_colour' => '#ff0000',
        'icon' => null, 'position_on_card' => CardPosition::TopLeft->value, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('promotion_label_translations')->insert([
        'promotion_label_id' => $labelId, 'locale' => 'en', 'text' => 'Original label',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = promotionLabelActor();

    Livewire::actingAs($actor)
        ->test(EditPromotionLabel::class, ['record' => $labelId])
        ->fillForm([
            'background_colour' => '#00ff00',
            'position_on_card' => CardPosition::BottomLeft->value,
            'is_active' => false,
            'translations' => ['en' => ['text' => 'Updated label']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $label = PromotionLabel::query()->findOrFail($labelId);

    expect($label->background_colour)->toBe('#00ff00')
        ->and($label->position_on_card)->toBe(CardPosition::BottomLeft)
        ->and($label->is_active)->toBeFalse()
        ->and(DB::table('promotion_label_translations')->where('promotion_label_id', $labelId)->where('locale', 'en')->value('text'))->toBe('Updated label');
});

it('lists promotion labels through the index page', function (): void {
    promotionLabelLanguage();
    DB::table('promotion_labels')->insert([
        'border_colour' => '#123456', 'text_colour' => '#ffffff', 'background_colour' => '#654321',
        'icon' => null, 'position_on_card' => CardPosition::TopRight->value, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $actor = promotionLabelActor();

    test()->actingAs($actor)
        ->get(PromotionLabelResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();
});

it('deletes a promotion label through its edit page delete action', function (): void {
    promotionLabelLanguage();

    $labelId = DB::table('promotion_labels')->insertGetId([
        'border_colour' => '#abcdef', 'text_colour' => '#ffffff', 'background_colour' => '#fedcba',
        'icon' => null, 'position_on_card' => CardPosition::TopLeft->value, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $actor = promotionLabelActor();

    Livewire::actingAs($actor)
        ->test(EditPromotionLabel::class, ['record' => $labelId])
        ->callAction('delete');

    expect(PromotionLabel::query()->find($labelId))->toBeNull();
});

it('grants every promotion label ability only to an unrestricted role, since the registry belongs to no single country', function (): void {
    $labelId = DB::table('promotion_labels')->insertGetId([
        'border_colour' => '#000000', 'text_colour' => '#ffffff', 'background_colour' => '#ff0000',
        'icon' => null, 'position_on_card' => CardPosition::TopLeft->value, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $label = PromotionLabel::query()->findOrFail($labelId);

    $unrestricted = promotionLabelActor();

    expect($unrestricted->can('viewAny', PromotionLabel::class))->toBeTrue()
        ->and($unrestricted->can('view', $label))->toBeTrue()
        ->and($unrestricted->can('create', PromotionLabel::class))->toBeTrue()
        ->and($unrestricted->can('update', $label))->toBeTrue()
        ->and($unrestricted->can('delete', $label))->toBeTrue();

    $languageId = DB::table('languages')->insertGetId([
        'code' => 'ro', 'short_label' => 'RO', 'text_direction' => 'ltr',
        'is_active' => false, 'is_primary' => false, 'display_order' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId,
        'is_active' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryScoped = promotionLabelActor(scopeKind: 'country', scopeReferenceId: $countryId);

    expect($countryScoped->can('viewAny', PromotionLabel::class))->toBeFalse()
        ->and($countryScoped->can('view', $label))->toBeFalse()
        ->and($countryScoped->can('create', PromotionLabel::class))->toBeFalse()
        ->and($countryScoped->can('update', $label))->toBeFalse()
        ->and($countryScoped->can('delete', $label))->toBeFalse();
});

it('refuses every ability to a role holding no advertising permission at all', function (): void {
    $labelId = DB::table('promotion_labels')->insertGetId([
        'border_colour' => '#000000', 'text_colour' => '#ffffff', 'background_colour' => '#ff0000',
        'icon' => null, 'position_on_card' => CardPosition::TopLeft->value, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $label = PromotionLabel::query()->findOrFail($labelId);

    $stranger = promotionLabelActor(['admin_panel_access']);

    expect($stranger->can('viewAny', PromotionLabel::class))->toBeFalse()
        ->and($stranger->can('view', $label))->toBeFalse()
        ->and($stranger->can('create', PromotionLabel::class))->toBeFalse()
        ->and($stranger->can('update', $label))->toBeFalse()
        ->and($stranger->can('delete', $label))->toBeFalse();
});
