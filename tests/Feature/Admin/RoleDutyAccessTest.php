<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\CommerceReports;
use App\Filament\Admin\Pages\PortalSettings;
use App\Filament\Admin\Resources\Banners\BannerResource;
use App\Filament\Admin\Resources\BannerSlots\BannerSlotResource;
use App\Filament\Admin\Resources\Languages\LanguageResource;
use App\Filament\Admin\Resources\Modules\ModuleResource;
use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Filament\Admin\Resources\PromotionLabels\PromotionLabelResource;
use App\Models\User;
use App\Services\Authorization\RoleGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Seeded Role Duties — F-09
|--------------------------------------------------------------------------
|
| RoleSeeder's permission grants must match what each role's own name
| implies. This is "the matrix as an executable table" F-09's own fix
| calls for: an unrestricted grant of each real seeded role, checked
| against the panel pages the role's duties should and should not reach.
|
*/

function roleDutyActor(string $roleKey): User
{
    $granter = User::where('email', 'test@example.com')->firstOrFail();
    $subject = User::factory()->create();

    app(RoleGrantService::class)->grantRole($subject, $roleKey, $granter);

    return $subject->fresh();
}

beforeEach(function (): void {
    Artisan::call('db:seed');
});

it('lets advertising_manager reach banners, banner slots, and promotion labels', function (): void {
    $actor = roleDutyActor('advertising_manager');

    test()->actingAs($actor)->get(BannerResource::getUrl('index', panel: 'admin'))->assertSuccessful();
    test()->actingAs($actor)->get(BannerSlotResource::getUrl('index', panel: 'admin'))->assertSuccessful();
    test()->actingAs($actor)->get(PromotionLabelResource::getUrl('index', panel: 'admin'))->assertSuccessful();
});

it('lets technical_support reach owners — the entry point impersonate needs — but refuses portal-settings, languages, and modules', function (): void {
    $actor = roleDutyActor('technical_support');

    test()->actingAs($actor)->get(OwnerResource::getUrl('index', panel: 'admin'))->assertSuccessful();
    test()->actingAs($actor)->get(PortalSettings::getUrl(panel: 'admin'))->assertForbidden();
    test()->actingAs($actor)->get(LanguageResource::getUrl('index', panel: 'admin'))->assertForbidden();
    test()->actingAs($actor)->get(ModuleResource::getUrl('index', panel: 'admin'))->assertForbidden();
});

it('lets finance_manager reach the commerce reports page', function (): void {
    $actor = roleDutyActor('finance_manager');

    test()->actingAs($actor)->get(CommerceReports::getUrl(panel: 'admin'))->assertSuccessful();
});

it('lets country_administrator reach owners', function (): void {
    $actor = roleDutyActor('country_administrator');

    test()->actingAs($actor)->get(OwnerResource::getUrl('index', panel: 'admin'))->assertSuccessful();
});
