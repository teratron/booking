<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Reviews
|--------------------------------------------------------------------------
|
| The object page's reviews section: an aggregate score plus itemized,
| published reviews with owner replies, present only when the reviews
| module resolves enabled for the object's own scope — absent, never an
| empty block, otherwise.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, typeId: int} */
function publicReviewsRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'locale' => 'en', 'name' => 'Capital City', 'slug' => 'capital-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_rooms' => true, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'typeId');
}

/** @param  array<string, mixed>  $overrides */
function publicReviewsMakeObject(array $fixture, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

/** @param  array<string, mixed>  $overrides */
function publicReviewsGiveReview(Object_ $object, array $overrides = []): int
{
    return DB::table('reviews')->insertGetId(array_merge([
        'object_id' => $object->id, 'rating' => 5, 'body' => 'A wonderful stay overall.',
        'author_name' => 'A Guest', 'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

function publicReviewsSeedModule(string $defaultState): int
{
    return DB::table('modules')->insertGetId([
        'key' => 'reviews', 'default_state' => $defaultState,
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('shows an aggregate score plus itemized reviews, each carrying rating, text, author, date, and an owner reply where one exists', function (): void {
    publicReviewsSeedModule('enabled');
    $fixture = publicReviewsRegistry();
    $object = publicReviewsMakeObject($fixture, 'Reviewed Hotel');

    $repliedAuthor = User::factory()->create(['name' => 'Registered Traveller']);
    publicReviewsGiveReview($object, [
        'rating' => 5, 'body' => 'Exceptional service throughout our stay.',
        'author_id' => $repliedAuthor->id, 'author_name' => null,
        'owner_reply' => 'Thank you for staying with us!', 'owner_replied_at' => now(),
    ]);
    publicReviewsGiveReview($object, [
        'rating' => 3, 'body' => 'Decent but noisy at night.', 'author_name' => 'Anonymous Guest',
    ]);

    $response = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $object]));

    $response->assertOk()
        ->assertSee('4.0 / 5')
        ->assertSee(__('public.object.reviews.heading'))
        ->assertSee('Exceptional service throughout our stay.')
        ->assertSee('Registered Traveller')
        ->assertSee('5 / 5')
        ->assertSee('Thank you for staying with us!')
        ->assertSee(__('public.object.reviews.owner_reply'))
        ->assertSee('Decent but noisy at night.')
        ->assertSee('Anonymous Guest')
        ->assertSee('3 / 5');
});

it('renders without an empty review block for an object with zero reviews', function (): void {
    publicReviewsSeedModule('enabled');
    $fixture = publicReviewsRegistry();
    $object = publicReviewsMakeObject($fixture, 'Unreviewed Hotel');

    $response = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $object]));

    $response->assertOk()->assertDontSee(__('public.object.reviews.heading'));
});

it('shows the reviews section only when the module resolves enabled for the object scope, never when disabled — the same review either way', function (): void {
    $fixture = publicReviewsRegistry();

    $moduleId = publicReviewsSeedModule('enabled');
    $enabledObject = publicReviewsMakeObject($fixture, 'Enabled Scope Hotel');
    publicReviewsGiveReview($enabledObject, ['body' => 'Gated review content.']);

    $disabledObject = publicReviewsMakeObject($fixture, 'Disabled Scope Hotel');
    publicReviewsGiveReview($disabledObject, ['body' => 'Gated review content.']);
    DB::table('module_settings')->insert([
        'module_id' => $moduleId, 'scope_level' => 'object', 'scope_reference_id' => $disabledObject->id,
        'state' => 'disabled', 'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $enabledResponse = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $enabledObject]));
    $disabledResponse = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $disabledObject]));

    $enabledResponse->assertOk()->assertSee(__('public.object.reviews.heading'))->assertSee('Gated review content.');
    $disabledResponse->assertOk()->assertDontSee(__('public.object.reviews.heading'))->assertDontSee('Gated review content.');

    // The aggregate score is part of the same gated section, not a
    // separate, always-visible teaser — a visitor must not learn a
    // disabled scope's rating through the page header.
    $disabledResponse->assertDontSee('5.0 / 5');
});

it('renders only published reviews — a pending or rejected review never appears', function (): void {
    publicReviewsSeedModule('enabled');
    $fixture = publicReviewsRegistry();
    $object = publicReviewsMakeObject($fixture, 'Moderated Reviews Hotel');

    publicReviewsGiveReview($object, ['body' => 'Published and visible.', 'status' => 'published']);
    publicReviewsGiveReview($object, ['body' => 'Still awaiting moderation.', 'status' => 'pending']);
    publicReviewsGiveReview($object, ['body' => 'Rejected by moderation.', 'status' => 'rejected']);

    $response = $this->get(route('public.objects.show', ['lang' => 'en', 'object' => $object]));

    $response->assertOk()
        ->assertSee('Published and visible.')
        ->assertDontSee('Still awaiting moderation.')
        ->assertDontSee('Rejected by moderation.');
});
