<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Scopes\ModerationScope;
use App\Models\User;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Integrations\MapTileConfigResolver;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

it('ignores a literal "null" query parameter instead of matching it as a real filter value', function (): void {
    // F-03/S-03: the catalog's own Livewire round trip dispatches its filter
    // state including PHP nulls; the map's JS spreads that into
    // URLSearchParams, which stringifies null to the four-character string
    // "null" -- Request::filled() sees a non-empty string and passes it
    // through, so every catalog-map render (the default, filterless state)
    // resolved objectTypeId to (int) "null" === 0 and matched nothing.
    // Reproduced here at the HTTP layer, the same shape the real request
    // carries, not by calling the controller with clean values.
    $fixture = publicMapGeography();
    $object = publicMapMakeObject($fixture, $fixture['typeId'], 'Should Still Appear Hotel', 47.0, 28.8);

    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 46.5, 'sw_lng' => 28.0, 'ne_lat' => 47.5, 'ne_lng' => 29.5,
        'type' => 'null', 'q' => 'null',
    ]));

    $response->assertOk();
    expect(collect($response->json('pins'))->pluck('id')->all())->toBe([$object->id]);
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

it('picks up a tile key already set in .env, with no administrator settings-panel step', function (): void {
    // F-16: nothing imported the documented .env variables into the
    // settings registry, so a fresh clone's MAP_TILE_KEY was silently
    // ignored — the map stayed dead until someone visited the settings
    // screen. config/booking.php now wires .env in as the setting's own
    // *default*, so this needs no administrator action at all.
    config(['booking.integrations.map_tile_key' => 'env-sourced-key']);

    $resolver = app(MapTileConfigResolver::class);

    expect($resolver->hasKey())->toBeTrue()
        ->and($resolver->styleUrl())->toContain('env-sourced-key');
});

it('renders a labelled placeholder instead of a broken tile request when no key is configured at all', function (): void {
    config(['booking.integrations.map_tile_key' => '']);

    $html = (string) $this->blade('<x-public.map />');

    expect($html)->toContain(__('public.shell.map.unavailable'))
        ->and($html)->not->toContain('catalogMap(');
});

it('renders the real map component once a key is configured', function (): void {
    config(['booking.integrations.map_tile_key' => 'env-sourced-key']);

    $html = (string) $this->blade('<x-public.map />');

    expect($html)->toContain('catalogMap(')
        ->and($html)->not->toContain(__('public.shell.map.unavailable'));
});

/*
|--------------------------------------------------------------------------
| Server-Side Clustering & Pin Cap
|--------------------------------------------------------------------------
|
| A country-wide viewport used to serialise every matching object as an
| individual pin — measured at over two megabytes for the demo volume's own
| ~52,800 seeded points, all clustered client-side after the fact. Below
| CatalogQueryService::CLUSTER_THRESHOLD_ZOOM the endpoint now aggregates to
| grid-cell centroids server-side instead; at or past it, individual pins
| are still returned but capped, with the response always carrying a
| `truncated` flag (never omitted, true or false) and the true match count.
|
*/

/**
 * Bulk-inserts `$count` minimal published objects, each a tiny fraction of
 * a degree apart -- enough to be distinct pins within one viewport, without
 * paying `User::factory()`'s own per-row bcrypt cost for an owner nothing
 * here reads.
 */
function publicMapBulkInsertPublishedObjects(array $fixture, int $typeId, int $ownerId, int $count): void
{
    $now = now();
    $buffer = [];

    for ($i = 0; $i < $count; $i++) {
        $lat = 47.0 + ($i * 0.0001);
        $lng = 28.8 + ($i * 0.0001);

        $buffer[] = [
            'ulid' => (string) Str::ulid(),
            'owner_id' => $ownerId,
            'object_type_id' => $typeId,
            'territory_id' => $fixture['territoryId'],
            'country_id' => $fixture['countryId'],
            'status' => 'published',
            'moderation_status' => 'approved',
            // @phpstan-ignore argument.type
            'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    DB::table('objects')->insert($buffer);
}

it('returns cluster centroids with counts rather than individual pins for a country-wide viewport below the clustering threshold', function (): void {
    $fixture = publicMapGeography();
    publicMapMakeObject($fixture, $fixture['typeId'], 'Cluster Member One', 47.000, 28.800);
    publicMapMakeObject($fixture, $fixture['typeId'], 'Cluster Member Two', 47.001, 28.801);
    publicMapMakeObject($fixture, $fixture['typeId'], 'Cluster Member Three', 47.002, 28.802);

    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 46.5, 'sw_lng' => 28.0, 'ne_lat' => 47.5, 'ne_lng' => 29.5,
        'zoom' => CatalogQueryService::CLUSTER_THRESHOLD_ZOOM - 1,
    ]));

    $response->assertOk();
    expect($response->json())->toHaveKey('clusters')->not->toHaveKey('pins');

    $clusters = $response->json('clusters');
    expect($clusters)->toHaveCount(1);
    expect($clusters[0])->toHaveKeys(['lat', 'lng', 'count'])
        ->and($clusters[0]['count'])->toBe(3);
});

it('returns individual pins in the existing shape once the zoom reaches the clustering threshold', function (): void {
    $fixture = publicMapGeography();
    $object = publicMapMakeObject($fixture, $fixture['typeId'], 'Threshold Zoom Hotel', 47.0, 28.8);

    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 46.5, 'sw_lng' => 28.0, 'ne_lat' => 47.5, 'ne_lng' => 29.5,
        'zoom' => CatalogQueryService::CLUSTER_THRESHOLD_ZOOM,
    ]));

    $response->assertOk();
    expect($response->json())->toHaveKey('pins')->not->toHaveKey('clusters');
    expect(collect($response->json('pins'))->pluck('id')->all())->toBe([$object->id]);
    expect($response->json('truncated'))->toBeFalse();
    expect($response->json('total'))->toBe(1);
});

it('caps individual pins at the configured maximum and flags the response as truncated once the match count exceeds it', function (): void {
    $fixture = publicMapGeography();
    $ownerId = User::factory()->create()->id;
    $cap = CatalogQueryService::MAX_PINS_PER_RESPONSE;
    publicMapBulkInsertPublishedObjects($fixture, $fixture['typeId'], $ownerId, $cap + 5);

    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 46.5, 'sw_lng' => 28.0, 'ne_lat' => 47.5, 'ne_lng' => 29.5,
        'zoom' => CatalogQueryService::CLUSTER_THRESHOLD_ZOOM,
    ]));

    $response->assertOk();
    expect($response->json('pins'))->toHaveCount($cap)
        ->and($response->json('truncated'))->toBeTrue()
        ->and($response->json('total'))->toBe($cap + 5);
});

it('reports the full untruncated count when the matched set is within the pin cap', function (): void {
    $fixture = publicMapGeography();
    $ownerId = User::factory()->create()->id;
    publicMapBulkInsertPublishedObjects($fixture, $fixture['typeId'], $ownerId, 3);

    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 46.5, 'sw_lng' => 28.0, 'ne_lat' => 47.5, 'ne_lng' => 29.5,
        'zoom' => CatalogQueryService::CLUSTER_THRESHOLD_ZOOM,
    ]));

    $response->assertOk();
    expect($response->json('pins'))->toHaveCount(3)
        ->and($response->json('truncated'))->toBeFalse()
        ->and($response->json('total'))->toBe(3);
});

it('keeps a country-wide response under a fixed byte ceiling at realistic seeded volume', function (): void {
    $this->seed();
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoVolumeSeeder', '--force' => true]);

    expect(DB::table('objects')->count())->toBeGreaterThanOrEqual(50_000);

    // Wide enough to span all three launch countries' own seeded bounds
    // (MD/UA/GE), the same "whole map, zoomed all the way out" viewport a
    // visitor lands on before picking a destination.
    $response = $this->getJson('/en/map/pins?'.http_build_query([
        'sw_lat' => 38.0, 'sw_lng' => 20.0, 'ne_lat' => 53.5, 'ne_lng' => 47.5,
        'zoom' => CatalogQueryService::CLUSTER_THRESHOLD_ZOOM - 1,
    ]));

    $response->assertOk();
    expect($response->json())->toHaveKey('clusters')->not->toHaveKey('pins');

    $byteSize = strlen((string) $response->getContent());

    // Comfortably above what a few dozen grid-cell clusters ever serialise
    // to, comfortably below the >2MB this same viewport measured before
    // clustering existed -- the actual regression this test guards against.
    expect($byteSize)->toBeLessThan(50_000);

    fwrite(STDERR, "\n".json_encode([
        'seeded_objects' => DB::table('objects')->count(),
        'response_bytes' => $byteSize,
        'cluster_count' => count($response->json('clusters')),
    ], JSON_PRETTY_PRINT)."\n");
})->group('slow');
