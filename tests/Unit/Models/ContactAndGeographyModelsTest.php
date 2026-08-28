<?php

declare(strict_types=1);

use App\Models\AvailabilityHistory;
use App\Models\BumpEvent;
use App\Models\ContactChannel;
use App\Models\ContactChannelType;
use App\Models\Country;
use App\Models\Object_;
use App\Models\ObjectPromotion;
use App\Models\ObjectType;
use App\Models\PlacementPackage;
use App\Models\PromotionLabel;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ContactChannel / ContactChannelType / Country / AvailabilityHistory /
| BumpEvent / ObjectPromotion
|--------------------------------------------------------------------------
|
| None of these six models carries a custom scope or accessor — each is a
| thin Eloquent shape. What is genuinely custom, and worth proving rather
| than assuming, is: the boolean/decimal/date casts actually convert what
| Postgres stores (not just echo back whatever PHP value was assigned);
| AvailabilityHistory's $timestamps = false actually matches a table with
| no timestamp columns, rather than silently failing to write them; and
| BumpEvent's polymorphic `scope` relation actually resolves to both of the
| two real morph targets the docblock claims (Territory and ObjectType) —
| a pivot-shaped business rule, not incidental relation wiring.
|
*/

/** @return array{countryId: int, territoryId: int, objectTypeId: int} */
function geoModelsFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['countryId' => $countryId, 'territoryId' => $territoryId, 'objectTypeId' => $objectTypeId];
}

/** A published, already-approved object — so a plain belongsTo() read finds it without withUnmoderated(). */
function geoModelsObject(int $countryId, int $territoryId, int $objectTypeId, ?int $ownerId = null): int
{
    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $objectTypeId,
        'territory_id' => $territoryId,
        'country_id' => $countryId,
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function geoModelsPlacementPackage(int $objectTypeId): int
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'object_type_id' => $objectTypeId, 'price' => 10,
        'currency' => 'EUR', 'validity_days' => 30, 'bump_allowed' => true, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function geoModelsPromotionLabel(): int
{
    return DB::table('promotion_labels')->insertGetId([
        'border_colour' => '#ffffff', 'text_colour' => '#000000', 'background_colour' => '#ff0000',
        'position_on_card' => 'top-left', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

// -----------------------------------------------------------------------
// ContactChannel
// -----------------------------------------------------------------------

it('casts ContactChannel.is_active to a real boolean from whatever Postgres stored', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $typeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'whatsapp', 'link_template' => 'https://wa.me/{value}', 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $activeId = DB::table('contact_channels')->insertGetId([
        'object_id' => $objectId, 'contact_channel_type_id' => $typeId, 'raw_value' => '+37360000001',
        'is_active' => 1, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $inactiveId = DB::table('contact_channels')->insertGetId([
        'object_id' => $objectId, 'contact_channel_type_id' => $typeId, 'raw_value' => '+37360000002',
        'is_active' => 0, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $active = ContactChannel::query()->findOrFail($activeId);
    $inactive = ContactChannel::query()->findOrFail($inactiveId);

    expect($active->is_active)->toBeBool()->toBeTrue()
        ->and($inactive->is_active)->toBeBool()->toBeFalse();
});

it('resolves ContactChannel.object and ContactChannel.contactChannelType to the correct records', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $typeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'viber', 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $channel = ContactChannel::create([
        'object_id' => $objectId, 'contact_channel_type_id' => $typeId,
        'raw_value' => '+37360000003', 'is_active' => true, 'display_order' => 0,
    ]);

    expect($channel->object())->toBeInstanceOf(BelongsTo::class)
        ->and($channel->object)->toBeInstanceOf(Object_::class)
        ->and($channel->object->id)->toBe($objectId)
        ->and($channel->contactChannelType())->toBeInstanceOf(BelongsTo::class)
        ->and($channel->contactChannelType)->toBeInstanceOf(ContactChannelType::class)
        ->and($channel->contactChannelType->id)->toBe($typeId);
});

// -----------------------------------------------------------------------
// ContactChannelType
// -----------------------------------------------------------------------

it('casts ContactChannelType.is_active to boolean and resolves its translated display_name', function (): void {
    // The translation row's own locale column carries a foreign key to
    // languages.code — without this row the save below would fail with a
    // foreign key violation rather than exercising the cast/translation
    // logic this test is actually about.
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $type = ContactChannelType::create([
        'key' => 'telegram', 'link_template' => 'https://t.me/{value}', 'is_active' => 1, 'display_order' => 0,
    ]);
    $type->translateOrNew('en')->display_name = 'Telegram';
    $type->save();

    $reloaded = ContactChannelType::query()->findOrFail($type->id);

    expect($reloaded->is_active)->toBeBool()->toBeTrue()
        ->and($reloaded->display_name)->toBe('Telegram');
});

it('lists every ContactChannel attached to a ContactChannelType through its hasMany relation', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $type = ContactChannelType::create(['key' => 'email', 'is_active' => true, 'display_order' => 0]);

    $first = ContactChannel::create([
        'object_id' => $objectId, 'contact_channel_type_id' => $type->id,
        'raw_value' => 'owner@example.test', 'is_active' => true, 'display_order' => 0,
    ]);
    $second = ContactChannel::create([
        'object_id' => $objectId, 'contact_channel_type_id' => $type->id,
        'raw_value' => 'sales@example.test', 'is_active' => true, 'display_order' => 1,
    ]);

    expect($type->contactChannels())->toBeInstanceOf(HasMany::class);

    $ids = $type->contactChannels()->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$first->id, $second->id]);
});

// -----------------------------------------------------------------------
// Country
// -----------------------------------------------------------------------

it('casts Country.is_active to boolean and resolves its translated name', function (): void {
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $country = Country::create([
        'code' => 'GE', 'currency' => 'GEL', 'phone_code' => '+995',
        'primary_language_id' => $languageId, 'is_active' => 1, 'display_order' => 0,
    ]);
    $country->translateOrNew('en')->name = 'Georgia';
    $country->save();

    $reloaded = Country::query()->findOrFail($country->id);

    expect($reloaded->is_active)->toBeBool()->toBeTrue()
        ->and($reloaded->name)->toBe('Georgia');
});

it('lists a Country\'s own territories and territory levels through their hasMany relations, not another country\'s', function (): void {
    $fixture = geoModelsFixture();
    $otherCountryId = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => DB::table('languages')->insertGetId([
            'code' => 'uk', 'short_label' => 'UK', 'is_active' => false, 'is_primary' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'is_active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // A second level and territory belonging to the unrelated country — must
    // never surface through the first country's own relations.
    $otherLevelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $otherCountryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territories')->insert([
        'country_id' => $otherCountryId, 'level_id' => $otherLevelId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $country = Country::query()->findOrFail($fixture['countryId']);

    expect($country->territories())->toBeInstanceOf(HasMany::class)
        ->and($country->territoryLevels())->toBeInstanceOf(HasMany::class)
        ->and($country->territories()->pluck('id')->all())->toBe([$fixture['territoryId']])
        ->and($country->territoryLevels()->count())->toBe(1);
});

// -----------------------------------------------------------------------
// AvailabilityHistory
// -----------------------------------------------------------------------

it('persists AvailabilityHistory with $timestamps disabled, matching a table with no timestamp columns', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);

    $history = AvailabilityHistory::create([
        'object_id' => $objectId, 'from_status' => null, 'to_status' => 'available',
        'changed_at' => now(), 'changed_by' => null, 'source' => 'automatic',
    ]);

    // If $timestamps were true, Eloquent would try to write created_at/
    // updated_at into a table that declares neither column, and the insert
    // above would have failed outright rather than reaching this line.
    expect($history->exists)->toBeTrue()
        ->and($history->getAttributes())->not->toHaveKey('created_at')
        ->and($history->getAttributes())->not->toHaveKey('updated_at')
        ->and($history->changed_at)->toBeInstanceOf(Carbon::class);
});

it('resolves AvailabilityHistory.object and AvailabilityHistory.changedBy, the latter nullable', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $staff = User::factory()->create();

    $withActor = AvailabilityHistory::create([
        'object_id' => $objectId, 'from_status' => 'available', 'to_status' => 'unavailable',
        'changed_at' => now(), 'changed_by' => $staff->id, 'source' => 'administrator',
    ]);
    $withoutActor = AvailabilityHistory::create([
        'object_id' => $objectId, 'from_status' => null, 'to_status' => 'available',
        'changed_at' => now(), 'changed_by' => null, 'source' => 'owner',
    ]);

    expect($withActor->object())->toBeInstanceOf(BelongsTo::class)
        ->and($withActor->object->id)->toBe($objectId)
        ->and($withActor->changedBy)->toBeInstanceOf(User::class)
        ->and($withActor->changedBy->id)->toBe($staff->id)
        ->and($withoutActor->changedBy)->toBeNull();
});

// -----------------------------------------------------------------------
// BumpEvent
// -----------------------------------------------------------------------

it('casts BumpEvent.occurred_at to datetime and BumpEvent.price to a two-decimal string', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $packageId = geoModelsPlacementPackage($fixture['objectTypeId']);

    $bump = BumpEvent::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'scope_type' => Territory::class, 'scope_id' => $fixture['territoryId'],
        'occurred_at' => now(), 'type' => 'paid', 'actor_id' => null,
        'previous_position' => 5, 'new_position' => 1, 'price' => '12.5', 'comment' => null,
    ]);

    $reloaded = BumpEvent::query()->findOrFail($bump->id);

    expect($reloaded->occurred_at)->toBeInstanceOf(Carbon::class)
        ->and((string) $reloaded->price)->toBe('12.50');
});

it('resolves BumpEvent.scope polymorphically to a Territory or an ObjectType depending on the stored scope_type', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $packageId = geoModelsPlacementPackage($fixture['objectTypeId']);

    $territoryScoped = BumpEvent::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'scope_type' => Territory::class, 'scope_id' => $fixture['territoryId'],
        'occurred_at' => now(), 'type' => 'free', 'actor_id' => null,
        'previous_position' => null, 'new_position' => 1, 'price' => null, 'comment' => null,
    ]);
    $objectTypeScoped = BumpEvent::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'scope_type' => ObjectType::class, 'scope_id' => $fixture['objectTypeId'],
        'occurred_at' => now(), 'type' => 'automatic', 'actor_id' => null,
        'previous_position' => null, 'new_position' => 1, 'price' => null, 'comment' => null,
    ]);

    expect($territoryScoped->scope())->toBeInstanceOf(MorphTo::class)
        ->and($territoryScoped->scope)->toBeInstanceOf(Territory::class)
        ->and($territoryScoped->scope->id)->toBe($fixture['territoryId'])
        ->and($objectTypeScoped->scope)->toBeInstanceOf(ObjectType::class)
        ->and($objectTypeScoped->scope->id)->toBe($fixture['objectTypeId']);
});

it('resolves BumpEvent.object, BumpEvent.package, and BumpEvent.actor, the latter nullable', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $packageId = geoModelsPlacementPackage($fixture['objectTypeId']);
    $actor = User::factory()->create();

    $bump = BumpEvent::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'scope_type' => Territory::class, 'scope_id' => $fixture['territoryId'],
        'occurred_at' => now(), 'type' => 'owner', 'actor_id' => $actor->id,
        'previous_position' => null, 'new_position' => null, 'price' => null, 'comment' => null,
    ]);

    expect($bump->object)->toBeInstanceOf(Object_::class)
        ->and($bump->object->id)->toBe($objectId)
        ->and($bump->package)->toBeInstanceOf(PlacementPackage::class)
        ->and($bump->package->id)->toBe($packageId)
        ->and($bump->actor)->toBeInstanceOf(User::class)
        ->and($bump->actor->id)->toBe($actor->id);
});

// -----------------------------------------------------------------------
// ObjectPromotion
// -----------------------------------------------------------------------

it('casts ObjectPromotion date and integer fields, not just echoing back the PHP value assigned', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $labelId = geoModelsPromotionLabel();

    $promotion = ObjectPromotion::create([
        'object_id' => $objectId, 'promotion_label_id' => $labelId,
        'starts_at' => '2026-01-10', 'ends_at' => '2026-02-10',
        'granted_by' => null, 'weight' => '5',
    ]);

    $reloaded = ObjectPromotion::query()->findOrFail($promotion->id);

    expect($reloaded->starts_at)->toBeInstanceOf(Carbon::class)
        ->and($reloaded->starts_at->toDateString())->toBe('2026-01-10')
        ->and($reloaded->ends_at)->toBeInstanceOf(Carbon::class)
        ->and($reloaded->ends_at->toDateString())->toBe('2026-02-10')
        ->and($reloaded->weight)->toBeInt()
        ->and($reloaded->weight)->toBe(5);
});

it('resolves ObjectPromotion.object, ObjectPromotion.label, and ObjectPromotion.grantedBy, the latter nullable', function (): void {
    $fixture = geoModelsFixture();
    $objectId = geoModelsObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $labelId = geoModelsPromotionLabel();
    $administrator = User::factory()->create();

    $promotion = ObjectPromotion::create([
        'object_id' => $objectId, 'promotion_label_id' => $labelId,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'granted_by' => $administrator->id, 'weight' => 0,
    ]);

    expect($promotion->object)->toBeInstanceOf(Object_::class)
        ->and($promotion->object->id)->toBe($objectId)
        ->and($promotion->label)->toBeInstanceOf(PromotionLabel::class)
        ->and($promotion->label->id)->toBe($labelId)
        ->and($promotion->grantedBy)->toBeInstanceOf(User::class)
        ->and($promotion->grantedBy->id)->toBe($administrator->id);
});
