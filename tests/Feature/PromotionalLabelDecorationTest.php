<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\ObjectPromotion;
use App\Models\PromotionLabel;
use App\Models\Territory;
use App\Models\User;
use App\Services\Advertising\CardDecorationService;
use App\Services\Placement\PlacementOrderingService;
use App\Support\Advertising\AvailabilityBadge;
use App\Support\Advertising\CardDecorationPayload;
use App\Support\Advertising\CardPosition;
use App\Support\Advertising\PromotionLabelDecoration;
use App\Support\Advertising\TierBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Promotional Labels & Card Decoration
|--------------------------------------------------------------------------
|
| `PromotionLabel` / `ObjectPromotion` are card decoration, distinct from the
| unrelated, owner-authored `promotions` content entity that shares only a
| word with them. The cases below prove: a label round-trips every field;
| granting one to a standard-tier object never leaks into the catalog
| ordering query's own tier precedence; the label set is genuinely open;
| the assembled decoration payload's schema is closed to every forbidden
| treatment by construction, not by convention; and a preview is computable
| against form state that was never written to the database.
|
*/

function decorationLanguages(): void
{
    DB::table('languages')->insert([
        ['code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);
}

/**
 * Requires decorationLanguages() to have already run — every caller in this
 * file seeds languages first, and re-inserting an 'en' row here would
 * collide with both the unique code and the single-primary-language index.
 *
 * @return array{country: int, territory: int, type: int}
 */
function decorationFixtureGeography(bool $hasAvailabilityStatus = false): array
{
    $languageId = DB::table('languages')->where('code', 'en')->value('id');
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_availability_status' => $hasAvailabilityStatus,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['country' => $countryId, 'territory' => $territoryId, 'type' => $typeId];
}

function decorationMakeObject(int $countryId, int $territoryId, int $typeId): int
{
    $owner = User::factory()->create();

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function decorationMakePackage(int $rank): int
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#111111', 'badge_icon' => 'star',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function decorationPlaceObject(int $objectId, int $packageId): void
{
    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(30)->toDateString(),
        'internal_priority' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function decorationMakeLabel(string $text, CardPosition $position = CardPosition::TopLeft): PromotionLabel
{
    /** @var PromotionLabel $label */
    $label = PromotionLabel::query()->create([
        'border_colour' => '#ff0000',
        'text_colour' => '#ffffff',
        'background_colour' => '#00ff00',
        'icon' => 'star',
        'position_on_card' => $position->value,
        'is_active' => true,
    ]);

    $label->translations()->create(['locale' => 'en', 'text' => $text]);

    return $label;
}

it('round-trips every field of a promotional label: per-language text, three colours, icon, position, and active flag', function (): void {
    decorationLanguages();

    /** @var PromotionLabel $label */
    $label = PromotionLabel::query()->create([
        'border_colour' => '#ff0000',
        'text_colour' => '#00ff00',
        'background_colour' => '#0000ff',
        'icon' => 'star',
        'position_on_card' => CardPosition::TopRight->value,
        'is_active' => false,
    ]);

    $label->translations()->create(['locale' => 'en', 'text' => 'VIP']);
    $label->translations()->create(['locale' => 'ru', 'text' => 'VIP RU']);

    /** @var PromotionLabel $fresh */
    $fresh = PromotionLabel::query()->with('translations')->findOrFail($label->id);

    expect($fresh->border_colour)->toBe('#ff0000')
        ->and($fresh->text_colour)->toBe('#00ff00')
        ->and($fresh->background_colour)->toBe('#0000ff')
        ->and($fresh->icon)->toBe('star')
        ->and($fresh->position_on_card)->toBe(CardPosition::TopRight)
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->translate('en')?->text)->toBe('VIP')
        ->and($fresh->translate('ru')?->text)->toBe('VIP RU');
});

it('lets an administrator create an entirely new label variant with no code change required', function (): void {
    decorationLanguages();

    // A combination that matches none of the specification's own examples
    // ("VIP", "New", …) — proving the set is genuinely open, not a code
    // enum with a generous back-office label attached to it.
    $label = decorationMakeLabel('Midsummer Getaway Deal', CardPosition::BottomLeft);

    /** @var PromotionLabel $fresh */
    $fresh = PromotionLabel::query()->with('translations')->findOrFail($label->id);

    expect(PromotionLabel::query()->count())->toBe(1)
        ->and($fresh->text)->toBe('Midsummer Getaway Deal')
        ->and($fresh->position_on_card)->toBe(CardPosition::BottomLeft);
});

it('grants a promotional label without changing the object\'s placement tier, and it still sorts after every higher tier', function (): void {
    decorationLanguages();
    $geo = decorationFixtureGeography();

    $highTierPackage = decorationMakePackage(1); // rank 1 = highest
    $standardTierPackage = decorationMakePackage(3);

    $highObject = decorationMakeObject($geo['country'], $geo['territory'], $geo['type']);
    $standardObject = decorationMakeObject($geo['country'], $geo['territory'], $geo['type']);

    decorationPlaceObject($highObject, $highTierPackage);
    decorationPlaceObject($standardObject, $standardTierPackage);

    $packageBefore = DB::table('object_placements')->where('object_id', $standardObject)->value('placement_package_id');

    $label = decorationMakeLabel('Tourist\'s Choice');
    $granter = User::factory()->create();

    // A generous weight — if the promotion term ever leaked above the tier
    // term, this is exactly the value that would expose it.
    ObjectPromotion::query()->create([
        'object_id' => $standardObject,
        'promotion_label_id' => $label->id,
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDays(10)->toDateString(),
        'granted_by' => $granter->id,
        'weight' => 1000,
    ]);

    $packageAfter = DB::table('object_placements')->where('object_id', $standardObject)->value('placement_package_id');

    expect($packageAfter)->toBe($packageBefore);

    $territory = Territory::query()->findOrFail($geo['territory']);
    $ordered = app(PlacementOrderingService::class)
        ->apply(Object_::query(), $territory)
        ->pluck('id')
        ->all();

    expect($ordered)->toBe([$highObject, $standardObject]);
});

it('assembles a bounded decoration payload carrying at most one tier badge, one promotion label, and one availability badge', function (): void {
    decorationLanguages();
    $geo = decorationFixtureGeography(hasAvailabilityStatus: true);
    $package = decorationMakePackage(2);
    $objectId = decorationMakeObject($geo['country'], $geo['territory'], $geo['type']);
    decorationPlaceObject($objectId, $package);

    $labelOne = decorationMakeLabel('VIP');
    $labelTwo = decorationMakeLabel('New');
    $granter = User::factory()->create();

    // Two overlapping active grants for the same object — the assembler
    // must still surface exactly one PromotionLabelDecoration, never a
    // collection, because the payload has nowhere to put a second one.
    ObjectPromotion::query()->create([
        'object_id' => $objectId, 'promotion_label_id' => $labelOne->id,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(5)->toDateString(),
        'granted_by' => $granter->id, 'weight' => 1,
    ]);
    ObjectPromotion::query()->create([
        'object_id' => $objectId, 'promotion_label_id' => $labelTwo->id,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addDays(10)->toDateString(),
        'granted_by' => $granter->id, 'weight' => 2,
    ]);

    $object = Object_::query()->findOrFail($objectId);
    $payload = app(CardDecorationService::class)->forObject($object);

    expect($payload)->toBeInstanceOf(CardDecorationPayload::class)
        ->and($payload->tierBadge)->toBeInstanceOf(TierBadge::class)
        ->and($payload->promotionLabel)->toBeInstanceOf(PromotionLabelDecoration::class)
        ->and($payload->availabilityBadge)->toBeInstanceOf(AvailabilityBadge::class)
        ->and($payload->availabilityBadge?->status)->toBe('available');
});

it('never assembles an availability badge for an object category that does not track availability', function (): void {
    decorationLanguages();
    $geo = decorationFixtureGeography(hasAvailabilityStatus: false);
    $package = decorationMakePackage(2);
    $objectId = decorationMakeObject($geo['country'], $geo['territory'], $geo['type']);
    decorationPlaceObject($objectId, $package);

    $payload = app(CardDecorationService::class)->forObject(Object_::query()->findOrFail($objectId));

    expect($payload->availabilityBadge)->toBeNull();
});

it('never surfaces a promotional label whose grant window has not started or has already ended', function (): void {
    decorationLanguages();
    $geo = decorationFixtureGeography();
    $package = decorationMakePackage(2);
    $objectId = decorationMakeObject($geo['country'], $geo['territory'], $geo['type']);
    decorationPlaceObject($objectId, $package);

    $expiredLabel = decorationMakeLabel('Last Season');
    $futureLabel = decorationMakeLabel('Coming Soon');
    $granter = User::factory()->create();

    ObjectPromotion::query()->create([
        'object_id' => $objectId, 'promotion_label_id' => $expiredLabel->id,
        'starts_at' => now()->subDays(20)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'granted_by' => $granter->id, 'weight' => 0,
    ]);
    ObjectPromotion::query()->create([
        'object_id' => $objectId, 'promotion_label_id' => $futureLabel->id,
        'starts_at' => now()->addDay()->toDateString(), 'ends_at' => now()->addDays(20)->toDateString(),
        'granted_by' => $granter->id, 'weight' => 0,
    ]);

    $payload = app(CardDecorationService::class)->forObject(Object_::query()->findOrFail($objectId));

    expect($payload->promotionLabel)->toBeNull();
});

it('keeps the decoration payload schema closed to blinking, animation, free-form CSS, and arbitrary size fields', function (): void {
    $reflection = new ReflectionClass(PromotionLabelDecoration::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameters = $constructor instanceof ReflectionMethod ? $constructor->getParameters() : [];
    $propertyNames = array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $parameters);

    // The bounded, closed set this class is allowed to carry.
    expect($propertyNames)->toEqualCanonicalizing([
        'borderColour', 'textColour', 'backgroundColour', 'icon', 'position', 'text',
    ]);

    // None of the forbidden treatments the specification names have a field
    // to encode them in — asserted by their genuine absence, not a comment.
    foreach (['animate', 'animation', 'blink', 'css', 'style', 'size', 'width', 'height', 'className'] as $forbidden) {
        expect($propertyNames)->not->toContain($forbidden);
    }

    $positionParameter = collect($parameters)->firstWhere('name', 'position');
    $positionType = $positionParameter?->getType();

    expect($positionType)->toBeInstanceOf(ReflectionNamedType::class);

    $positionTypeName = $positionType instanceof ReflectionNamedType ? $positionType->getName() : null;

    expect($positionTypeName)->toBe(CardPosition::class);

    // The enum itself is the fixed, small permitted set — not an open string.
    expect(array_map(static fn (CardPosition $case): string => $case->value, CardPosition::cases()))
        ->toEqualCanonicalizing(['top-left', 'top-right', 'bottom-left', 'bottom-right']);
});

it('computes a decorated-card preview before any database write', function (): void {
    // An unsaved model — never persisted, no ID — proving the preview does
    // not depend on a saved record to exist.
    $label = new PromotionLabel([
        'border_colour' => '#111111',
        'text_colour' => '#eeeeee',
        'background_colour' => '#ff00ff',
        'icon' => 'moon',
        'position_on_card' => CardPosition::BottomRight->value,
        'is_active' => true,
    ]);

    expect($label->exists)->toBeFalse()
        ->and($label->id)->toBeNull();

    $decoration = app(CardDecorationService::class)->previewLabel([
        'border_colour' => $label->border_colour,
        'text_colour' => $label->text_colour,
        'background_colour' => $label->background_colour,
        'icon' => $label->icon,
        'position_on_card' => $label->position_on_card,
        'text' => 'Draft Preview',
    ]);

    expect($decoration->borderColour)->toBe('#111111')
        ->and($decoration->textColour)->toBe('#eeeeee')
        ->and($decoration->backgroundColour)->toBe('#ff00ff')
        ->and($decoration->icon)->toBe('moon')
        ->and($decoration->position)->toBe(CardPosition::BottomRight)
        ->and($decoration->text)->toBe('Draft Preview');
});

it('refuses to preview a label whose position is missing or not one of the permitted positions', function (): void {
    expect(fn () => app(CardDecorationService::class)->previewLabel([
        'border_colour' => '#000000',
        'text_colour' => '#ffffff',
        'background_colour' => '#ffffff',
        'icon' => null,
        'position_on_card' => 'centre-of-the-photo',
        'text' => 'Whatever',
    ]))->toThrow(InvalidArgumentException::class);
});
