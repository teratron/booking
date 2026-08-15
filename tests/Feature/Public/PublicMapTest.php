<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Scopes\ModerationScope;
use App\Models\User;
use App\Services\Integrations\MapTileConfigResolver;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Map
|--------------------------------------------------------------------------
|
| The catalog map renders pins for the filtered result set only, never the
| full object table; a pin opens the same contact actions a list card
| carries; and the configured tile provider is never the OSMF-prohibited
| public OpenStreetMap tile host.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int, otherTypeId: int, channelTypeId: int} */
function publicMapGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
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
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Map City', 'slug' => 'map-city',
        'full_slug_path' => 'map-city',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_availability_status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherTypeId = DB::table('object_types')->insertGetId([
        'key' => 'restaurant', 'is_active' => true, 'has_availability_status' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $channelTypeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'phone', 'link_template' => 'tel:{value}', 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contact_channel_type_translations')->insert([
        'contact_channel_type_id' => $channelTypeId, 'locale' => 'en', 'display_name' => 'Phone',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId', 'otherTypeId', 'channelTypeId');
}

/** @param  array<string, mixed>  $overrides */
function publicMapMakeObject(array $fixture, int $typeId, string $name, float $lat, float $lng, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        // @phpstan-ignore argument.type
        'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'short_description' => 'A pin on the map.',
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('contact_channels')->insert([
        'object_id' => $objectId, 'contact_channel_type_id' => $fixture['channelTypeId'],
        'raw_value' => '+37360000000', 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Bypasses ModerationScope deliberately: a fixture built with a
    // draft/pending status must still be returned to the caller, which may
    // be asserting that a *different*, scope-respecting query excludes it.
    /** @var Object_ $object */
    $object = Object_::withoutGlobalScope(ModerationScope::class)->findOrFail($objectId);

    return $object;
}

it('renders pins for exactly the filtered result set within the viewport, not the full object table', function (): void {
    $fixture = publicMapGeography();
    $inViewportMatching = publicMapMakeObject($fixture, $fixture['typeId'], 'In Viewport Hotel', 47.0, 28.8);
    publicMapMakeObject($fixture, $fixture['typeId'], 'Outside Viewport Hotel', 10.0, 10.0);
    publicMapMakeObject($fixture, $fixture['otherTypeId'], 'Wrong Type Restaurant', 47.0, 28.8);

    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 46.5, 'sw_lng' => 28.0, 'ne_lat' => 47.5, 'ne_lng' => 29.5,
        'type' => $fixture['typeId'],
    ]));

    $response->assertOk();
    $ids = collect($response->json('pins'))->pluck('id')->all();

    expect($ids)->toBe([$inViewportMatching->id]);
});

it('never surfaces a draft or pending object as a pin, and refuses its compact card', function (): void {
    $fixture = publicMapGeography();
    $draft = publicMapMakeObject($fixture, $fixture['typeId'], 'Draft Hotel', 47.0, 28.8, [
        'status' => 'draft', 'moderation_status' => 'pending',
    ]);

    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 46.5, 'sw_lng' => 28.0, 'ne_lat' => 47.5, 'ne_lng' => 29.5,
    ]));

    expect($response->json('pins'))->toBe([]);

    $this->get("/en/map/pins/{$draft->id}")->assertNotFound();
});

it("opens a pin's compact card carrying the same contact actions as the list card", function (): void {
    $fixture = publicMapGeography();
    $object = publicMapMakeObject($fixture, $fixture['typeId'], 'Shared Actions Hotel', 47.0, 28.8);

    $listCardHtml = (string) $this->blade('<x-object-card :object="$object" />', ['object' => $object->fresh()]);
    $pinCardResponse = $this->get("/en/map/pins/{$object->id}");

    $pinCardResponse->assertOk();

    // Both cards route the same channel through the identical click-capture
    // redirect — the click itself is measured the same way regardless of
    // which surface the visitor contacted the owner from.
    $channelId = DB::table('contact_channels')->where('object_id', $object->id)->value('id');
    $clickUrl = route('public.objects.contact.click', ['lang' => 'en', 'object' => $object->id, 'channel' => $channelId]);

    expect($listCardHtml)->toContain($clickUrl);
    $pinCardResponse->assertSee($clickUrl, false);
});

it('never configures the OSMF-prohibited public OpenStreetMap tile host, for any provider setting', function (): void {
    $settings = app(SettingsRepository::class);
    $resolver = app(MapTileConfigResolver::class);

    foreach (['maptiler', 'stadia', 'unrecognised-provider', ''] as $provider) {
        $settings->set('integrations.map_tile_provider', $provider);

        expect($resolver->styleUrl())
            ->not->toContain('tile.openstreetmap.org')
            ->not->toContain('openstreetmap.org');
    }
});
