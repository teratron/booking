<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Filament\Admin\Resources\Objects\Pages\EditObject;
use App\Models\Object_;
use App\Models\PlacementPackage;
use App\Services\Placement\PlacementLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Placement Grant Administration
|--------------------------------------------------------------------------
|
| PlacementLifecycleService::grant()/pin()/unpin() were already correct and
| already tested at the service level (PlacementLifecycleAndOrderingTest);
| what this file proves is the panel surface that finally calls them — the
| object edit page's grant/pin/unpin actions, the bulk action's scoped
| permission, and the read-back history — none of which had any caller in
| app/Filament before this fix.
|
*/

it('grants a placement package from the object edit page and records the acting administrator\'s comment', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');
    $packageId = makePackage(2);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('grant_placement')
        ->setActionData([
            'placement_package_id' => $packageId,
            'ledger_status' => 'granted_free',
            'comment' => 'Comped for launch partner',
        ])
        ->callMountedAction();

    expect(DB::table('object_placements')->where('object_id', $fixture['objectId'])->value('placement_package_id'))
        ->toBe($packageId)
        ->and(DB::table('placement_histories')->where('object_id', $fixture['objectId'])->where('comment', 'Comped for launch partner')->exists())
        ->toBeTrue()
        ->and(DB::table('audits')->where('auditable_id', $fixture['objectId'])->where('event', 'placement_granted')->count())
        ->toBe(1);
});

it('replaces a prior grant when a second package is granted, closing the earlier history row rather than deleting it', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');
    $firstPackageId = makePackage(3);
    $secondPackageId = makePackage(1);

    $component = Livewire::actingAs($actor)->test(EditObject::class, ['record' => $fixture['objectId']]);

    $component->mountAction('grant_placement')
        ->setActionData(['placement_package_id' => $firstPackageId, 'ledger_status' => 'granted_free'])
        ->callMountedAction();

    $component->mountAction('grant_placement')
        ->setActionData(['placement_package_id' => $secondPackageId, 'ledger_status' => 'paid'])
        ->callMountedAction();

    expect(DB::table('object_placements')->where('object_id', $fixture['objectId'])->value('placement_package_id'))
        ->toBe($secondPackageId)
        ->and(DB::table('placement_histories')->where('object_id', $fixture['objectId'])->count())->toBe(2)
        ->and(DB::table('placement_histories')->where('object_id', $fixture['objectId'])->where('placement_package_id', $firstPackageId)->value('ends_at'))
        ->not->toBeNull();
});

it('pins and unpins the object\'s position from the edit page, only once a placement exists', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');
    $packageId = makePackage(1);

    $component = Livewire::actingAs($actor)->test(EditObject::class, ['record' => $fixture['objectId']]);

    // No placement yet — the pin action is not offered at all.
    $component->assertActionHidden('pin_placement');

    $component->mountAction('grant_placement')
        ->setActionData(['placement_package_id' => $packageId, 'ledger_status' => 'granted_free'])
        ->callMountedAction();

    $component = Livewire::actingAs($actor)->test(EditObject::class, ['record' => $fixture['objectId']]);
    $component->assertActionVisible('pin_placement')
        ->mountAction('pin_placement')
        ->setActionData(['position' => 3])
        ->callMountedAction();

    expect(DB::table('object_placements')->where('object_id', $fixture['objectId'])->value('pinned_position'))->toBe(3);

    $component = Livewire::actingAs($actor)->test(EditObject::class, ['record' => $fixture['objectId']]);
    $component->assertActionVisible('unpin_placement')
        ->callAction('unpin_placement');

    expect(DB::table('object_placements')->where('object_id', $fixture['objectId'])->value('pinned_position'))->toBeNull();
});

it('refuses the grant action to an administrator whose scope does not cover the object — refused by the policy, not just hidden', function (): void {
    $fixture = objectFormFixture();
    // Scoped to a category the fixture object does not belong to.
    $actor = objectFormActor(['admin_panel_access', 'object.view', 'commerce.edit'], 'category', $fixture['typeDining'], 'dining_commerce_only');

    expect($actor->can('grantPlacement', $fixture['object']))->toBeFalse();

    // The same scope narrowing that keeps the object off this actor's list
    // also governs the edit page's own record resolution — a category
    // mismatch reads as not found, before the grant action's own
    // ->authorize() closure ever runs.
    $this->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $fixture['objectId']], panel: 'admin'))
        ->assertNotFound();
});

it('lets a scoped administrator grant placement within their own scope', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(['admin_panel_access', 'object.view', 'commerce.edit'], 'country', $fixture['countryMd'], 'md_commerce_only');

    expect($actor->can('grantPlacement', $fixture['object']))->toBeTrue();
});

it('reads the object\'s placement history from the panel, oldest and newest grants alike', function (): void {
    $fixture = objectFormFixture();
    $object = Object_::query()->withUnmoderated()->findOrFail($fixture['objectId']);
    $firstPackageId = makePackage(3);
    $secondPackageId = makePackage(1);
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');

    app(PlacementLifecycleService::class)->grant($object, PlacementPackage::query()->findOrFail($firstPackageId), $actor, 'First grant');
    app(PlacementLifecycleService::class)->grant($object->fresh(), PlacementPackage::query()->findOrFail($secondPackageId), $actor, 'Second grant');

    expect($object->fresh()->placementHistories()->count())->toBe(2)
        ->and($object->fresh()->placementHistories()->where('comment', 'First grant')->exists())->toBeTrue()
        ->and($object->fresh()->placementHistories()->where('comment', 'Second grant')->exists())->toBeTrue();
});
