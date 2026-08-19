<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\DataImport;
use App\Models\Object_;
use App\Models\User;
use App\Services\DataTransfer\ImportPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Import Duplicate Detection
|--------------------------------------------------------------------------
|
| Five independent signals — name, phone, website, address, coordinates —
| each checked in isolation so a candidate is provably flagged for the
| specific reason a real operator would need to see, never a bare "possible
| duplicate". Detection is read-only throughout: every case that follows a
| preview with a real confirm asserts the flagged pairing still results in
| two separate objects, never an automatic merge.
|
*/

/** @return array{ownerId: int, countryId: int, territoryId: int, typeId: int} */
function duplicateFixtureScope(): array
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
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ownerId = User::factory()->create()->id;

    DB::table('contact_channel_types')->insert([
        ['key' => 'phone', 'is_active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'website', 'is_active' => true, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    return [
        'ownerId' => $ownerId,
        'countryId' => $countryId,
        'territoryId' => $territoryId,
        'typeId' => $typeId,
    ];
}

/**
 * @param  array{ownerId: int, countryId: int, territoryId: int, typeId: int}  $scope
 * @param  array<string, mixed>  $overrides
 */
function existingObject(array $scope, array $overrides = []): int
{
    return DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $scope['ownerId'],
        'object_type_id' => $scope['typeId'],
        'territory_id' => $scope['territoryId'],
        'country_id' => $scope['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function attachName(int $objectId, string $name): void
{
    DB::table('object_translations')->insert([
        'object_id' => $objectId,
        'locale' => 'en',
        'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function attachContactChannel(int $objectId, string $channelKey, string $rawValue): void
{
    $typeId = DB::table('contact_channel_types')->where('key', $channelKey)->value('id');

    DB::table('contact_channels')->insert([
        'object_id' => $objectId,
        'contact_channel_type_id' => $typeId,
        'raw_value' => $rawValue,
        'is_active' => true,
        'display_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * The registry column map every case shares: every "objects" column mapped
 * to a header of the same name, so a raw row can also carry extra headers
 * (name, phone, website) the map itself never references — the signal
 * extraction duplicate detection itself performs reads those directly by
 * alias instead.
 *
 * @return array<string, string|null>
 */
function duplicateColumnMap(): array
{
    return [
        'id' => null,
        'ulid' => 'ulid',
        'owner_id' => 'owner_id',
        'object_type_id' => 'object_type_id',
        'territory_id' => 'territory_id',
        'country_id' => 'country_id',
        'address' => 'address',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'status' => null,
        'availability_status' => null,
    ];
}

/**
 * @param  array{ownerId: int, countryId: int, territoryId: int, typeId: int}  $scope
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function newObjectRow(array $scope, array $extra = []): array
{
    return array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => (string) $scope['ownerId'],
        'object_type_id' => (string) $scope['typeId'],
        'territory_id' => (string) $scope['territoryId'],
        'country_id' => (string) $scope['countryId'],
    ], $extra);
}

it('flags a candidate by name similarity', function (): void {
    $scope = duplicateFixtureScope();
    $existingId = existingObject($scope);
    attachName($existingId, 'Hotel Astoria');

    $result = app(ImportPipelineService::class)->preview(
        'objects',
        [newObjectRow($scope, ['name' => 'Astoria Hotel'])],
        duplicateColumnMap(),
    );

    expect($result->duplicates)->toHaveCount(1);

    $candidate = $result->duplicates[0]->candidates[0];
    expect($candidate->objectId)->toBe($existingId)
        ->and($candidate->signalKinds())->toBe(['name']);
});

it('flags a candidate by phone number match', function (): void {
    $scope = duplicateFixtureScope();
    $existingId = existingObject($scope);
    attachContactChannel($existingId, 'phone', '+373 22 123 456');

    // Same digits, different formatting — a dash-separated number with no
    // country-code "+" is still the same phone number.
    $result = app(ImportPipelineService::class)->preview(
        'objects',
        [newObjectRow($scope, ['phone' => '373-22-123-456'])],
        duplicateColumnMap(),
    );

    expect($result->duplicates)->toHaveCount(1);

    $candidate = $result->duplicates[0]->candidates[0];
    expect($candidate->objectId)->toBe($existingId)
        ->and($candidate->signalKinds())->toBe(['phone']);
});

it('flags a candidate by website match', function (): void {
    $scope = duplicateFixtureScope();
    $existingId = existingObject($scope);
    attachContactChannel($existingId, 'website', 'https://www.astoria-hotel.md/');

    // No scheme, no "www.", different case — still the same site once
    // normalized.
    $result = app(ImportPipelineService::class)->preview(
        'objects',
        [newObjectRow($scope, ['website' => 'ASTORIA-HOTEL.MD'])],
        duplicateColumnMap(),
    );

    expect($result->duplicates)->toHaveCount(1);

    $candidate = $result->duplicates[0]->candidates[0];
    expect($candidate->objectId)->toBe($existingId)
        ->and($candidate->signalKinds())->toBe(['website']);
});

it('flags a candidate by address similarity', function (): void {
    $scope = duplicateFixtureScope();
    $existingId = existingObject($scope, ['address' => '12 Main Street, Chisinau']);

    $result = app(ImportPipelineService::class)->preview(
        'objects',
        [newObjectRow($scope, ['address' => '12 Main St, Chisinau'])],
        duplicateColumnMap(),
    );

    expect($result->duplicates)->toHaveCount(1);

    $candidate = $result->duplicates[0]->candidates[0];
    expect($candidate->objectId)->toBe($existingId)
        ->and($candidate->signalKinds())->toBe(['address']);
});

it('flags a candidate by coordinate proximity, computed from latitude/longitude when geom was never populated', function (): void {
    $scope = duplicateFixtureScope();

    // Deliberately no `geom` on this row — matching the real state of every
    // object this codebase's own create/edit forms and the objects importer
    // produce today, none of which writes that column. The signal must
    // still fire from latitude/longitude alone.
    $existingId = existingObject($scope, ['latitude' => 47.0105, 'longitude' => 28.8638]);

    $result = app(ImportPipelineService::class)->preview(
        'objects',
        // ~6.7 metres away — well inside the 100 m radius.
        [newObjectRow($scope, ['latitude' => '47.01055', 'longitude' => '28.86385'])],
        duplicateColumnMap(),
    );

    expect($result->duplicates)->toHaveCount(1);

    $candidate = $result->duplicates[0]->candidates[0];
    expect($candidate->objectId)->toBe($existingId)
        ->and($candidate->signalKinds())->toBe(['coordinates']);
});

it('does not pair two genuinely distinct objects in the same settlement', function (): void {
    $scope = duplicateFixtureScope();
    $existingId = existingObject($scope, ['address' => '15 Freedom Street', 'latitude' => 47.0105, 'longitude' => 28.8638]);
    attachName($existingId, 'Sunrise Guesthouse');
    attachContactChannel($existingId, 'phone', '+373 22 111 111');
    attachContactChannel($existingId, 'website', 'https://sunrise-guesthouse.md');

    // Same settlement (same territory), but a different name, address,
    // phone, website, and a pin roughly 1.1 km away — nothing here should
    // pair with the fixture above.
    $result = app(ImportPipelineService::class)->preview(
        'objects',
        [newObjectRow($scope, [
            'name' => 'Central Diner',
            'address' => '42 Victory Avenue',
            'phone' => '+373 22 999 999',
            'website' => 'central-diner.md',
            'latitude' => '47.0200',
            'longitude' => '28.8700',
        ])],
        duplicateColumnMap(),
    );

    expect($result->duplicates)->toBe([]);
});

it('writes zero merges of its own accord: a confirmed import with a flagged duplicate still creates two separate objects', function (): void {
    $scope = duplicateFixtureScope();
    $existingId = existingObject($scope);
    attachName($existingId, 'Hotel Astoria');

    $row = newObjectRow($scope, ['name' => 'Astoria Hotel']);
    $columnMap = duplicateColumnMap();

    $preview = app(ImportPipelineService::class)->preview('objects', [$row], $columnMap);
    expect($preview->duplicates)->toHaveCount(1)
        ->and($preview->createCount)->toBe(1);

    // The preview pass alone must not have written the new row.
    expect(Object_::query()->withUnmoderated()->count())->toBe(1);

    $actor = User::factory()->create();

    app(ImportPipelineService::class)->confirm(
        'objects',
        [$row],
        $columnMap,
        $actor,
        'objects.xlsx',
        'imports/objects.xlsx',
    );

    // Both the flagged existing object and the newly imported row exist as
    // two separate, untouched records — nothing merged, nothing archived,
    // nothing redirected. Merging is a distinct, administrator-confirmed
    // action this pipeline never takes on its own.
    expect(Object_::query()->withUnmoderated()->count())->toBe(2)
        ->and(Object_::query()->withUnmoderated()->find($existingId)?->trashed())->toBeFalse()
        ->and(DB::table('objects')->where('ulid', $row['ulid'])->exists())->toBeTrue()
        ->and(DB::table('redirects')->count())->toBe(0);
});

it('surfaces duplicate candidates in the admin import preview screen', function (): void {
    $scope = duplicateFixtureScope();
    $existingId = existingObject($scope);
    attachName($existingId, 'Hotel Astoria');

    $newUlid = (string) Str::ulid();
    $headers = ['ulid', 'owner_id', 'object_type_id', 'territory_id', 'country_id', 'name'];
    $rows = [[
        'ulid' => $newUlid,
        'owner_id' => (string) $scope['ownerId'],
        'object_type_id' => (string) $scope['typeId'],
        'territory_id' => (string) $scope['territoryId'],
        'country_id' => (string) $scope['countryId'],
        'name' => 'Astoria Hotel',
    ]];

    $path = tempnam(sys_get_temp_dir(), 'duplicate-import-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($headers));
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues(array_map(static fn (string $header): string => $row[$header], $headers)));
    }
    $writer->close();
    $content = (string) file_get_contents($path);
    @unlink($path);

    $file = UploadedFile::fake()->createWithContent('objects.xlsx', $content);

    Permission::findOrCreate('admin_panel_access', 'web');
    Permission::findOrCreate('object.create', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin_panel_access', 'object.create']);

    $component = Livewire::actingAs($actor)
        ->test(DataImport::class)
        ->set('kind', 'objects')
        ->set('file', $file)
        ->call('parseFile')
        ->assertSet('step', 'mapping')
        ->call('runPreview')
        ->assertSet('step', 'preview');

    expect($component->get('previewSummary')['duplicates'])->toBe(1);

    $duplicates = $component->get('previewDuplicates');
    expect($duplicates)->toHaveCount(1)
        ->and($duplicates[0]['row'])->toBe(1)
        ->and($duplicates[0]['candidates'][0]['label'])->toBe('Hotel Astoria');
});
