<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Review Submission
|--------------------------------------------------------------------------
|
| The write path behind reviews.submission_mode: `open` (CAPTCHA-gated,
| every visitor reachable) and `contact_gated` (reachable only after a
| contact-channel click for this object, this session). Both modes always
| enter a submitted review as status = 'pending' — the gate decides who may
| submit, never whether what they submit is published.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, typeId: int} */
function reviewSubmissionRegistry(): array
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
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Capital City', 'slug' => 'capital-city',
        'full_slug_path' => 'capital-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'typeId');
}

/** @param  array<string, mixed>  $fixture */
function reviewSubmissionMakeObject(array $fixture, string $name): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

/** @return array<string, mixed> */
function reviewSubmissionPayload(array $overrides = []): array
{
    return array_merge([
        'author_name' => 'Jane Visitor',
        'rating' => 5,
        'body' => 'A genuinely lovely stay, would come back.',
    ], $overrides);
}

it('creates a pending, unpublished review in open mode with no CAPTCHA provider configured', function (): void {
    $fixture = reviewSubmissionRegistry();
    $object = reviewSubmissionMakeObject($fixture, 'Open Mode Hotel');

    $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload())
        ->assertRedirect()
        ->assertSessionHas('public-review-submitted', true);

    $review = DB::table('reviews')->where('object_id', $object->id)->first();
    expect($review)->not->toBeNull()
        ->and($review->status)->toBe('pending')
        ->and($review->author_name)->toBe('Jane Visitor')
        ->and($review->rating)->toBe(5);

    // Pending, so not yet visible on the object's own public page.
    $this->get(publicObjectUrl($object))->assertOk()->assertDontSee('A genuinely lovely stay, would come back.');
});

it('refuses a submission with a failed CAPTCHA response in open mode, once a real provider is configured', function (): void {
    config(['booking.integrations.captcha_provider' => 'turnstile']);
    DB::table('settings')->insert([
        ['key' => 'integrations.captcha_provider', 'value' => json_encode('turnstile'), 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'integrations.captcha_secret', 'value' => json_encode('test-secret'), 'created_at' => now(), 'updated_at' => now()],
    ]);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $fixture = reviewSubmissionRegistry();
    $object = reviewSubmissionMakeObject($fixture, 'Captcha Refused Hotel');

    $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload(['cf-turnstile-response' => 'bogus']))
        ->assertRedirect()
        ->assertSessionHasErrors('review');

    expect(DB::table('reviews')->where('object_id', $object->id)->exists())->toBeFalse();
});

it('accepts a submission with a valid CAPTCHA response in open mode', function (): void {
    config(['booking.integrations.captcha_provider' => 'turnstile']);
    DB::table('settings')->insert([
        ['key' => 'integrations.captcha_provider', 'value' => json_encode('turnstile'), 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'integrations.captcha_secret', 'value' => json_encode('test-secret'), 'created_at' => now(), 'updated_at' => now()],
    ]);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    $fixture = reviewSubmissionRegistry();
    $object = reviewSubmissionMakeObject($fixture, 'Captcha Passed Hotel');

    $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload(['cf-turnstile-response' => 'genuine']))
        ->assertRedirect()
        ->assertSessionHas('public-review-submitted', true);

    expect(DB::table('reviews')->where('object_id', $object->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('refuses a submission in contact_gated mode without a prior contact click for this object', function (): void {
    DB::table('settings')->insert([
        'key' => 'reviews.submission_mode', 'value' => json_encode('contact_gated'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $fixture = reviewSubmissionRegistry();
    $object = reviewSubmissionMakeObject($fixture, 'Gate Closed Hotel');

    $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('review');

    expect(DB::table('reviews')->where('object_id', $object->id)->exists())->toBeFalse();
});

it('accepts a submission in contact_gated mode after a prior contact click, with no CAPTCHA required', function (): void {
    DB::table('settings')->insert([
        'key' => 'reviews.submission_mode', 'value' => json_encode('contact_gated'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // A real provider configured but never consulted in this mode — the
    // contact-click gate is the control, not a second CAPTCHA challenge.
    config(['booking.integrations.captcha_provider' => 'turnstile']);
    DB::table('settings')->insert([
        'key' => 'integrations.captcha_provider', 'value' => json_encode('turnstile'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $fixture = reviewSubmissionRegistry();
    $object = reviewSubmissionMakeObject($fixture, 'Gate Open Hotel');
    $phoneTypeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'phone', 'link_template' => 'tel:{value}', 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $channelId = DB::table('contact_channels')->insertGetId([
        'object_id' => $object->id, 'contact_channel_type_id' => $phoneTypeId,
        'raw_value' => '37360000000', 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get(route('public.objects.contact.click', ['lang' => 'en', 'object' => $object, 'channel' => $channelId]))
        ->assertRedirect('tel:37360000000');

    $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload())
        ->assertRedirect()
        ->assertSessionHas('public-review-submitted', true);

    expect(DB::table('reviews')->where('object_id', $object->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('rejects a submission missing a required field', function (): void {
    $fixture = reviewSubmissionRegistry();
    $object = reviewSubmissionMakeObject($fixture, 'Validation Hotel');

    $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload(['body' => '']))
        ->assertSessionHasErrors('body');

    expect(DB::table('reviews')->where('object_id', $object->id)->exists())->toBeFalse();
});

it('rate-limits repeated submissions from the same IP', function (): void {
    $fixture = reviewSubmissionRegistry();
    $object = reviewSubmissionMakeObject($fixture, 'Rate Limited Hotel');

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload())
            ->assertRedirect();
    }

    $this->post(route('public.objects.reviews.submit', ['lang' => 'en', 'object' => $object]), reviewSubmissionPayload())
        ->assertStatus(429);
});
