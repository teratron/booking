<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\DataImport;
use App\Filament\Admin\Resources\Objects\Pages\ListObjects;
use App\Models\Object_;
use App\Models\User;
use App\Services\DataTransfer\ImportKindRegistry;
use App\Services\DataTransfer\ImportPipelineService;
use App\Services\DataTransfer\TransferableRegistry;
use Filament\Actions\Exports\Downloaders\CsvDownloader;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Import & Export Invariants
|--------------------------------------------------------------------------
|
| The cross-track assertions the import pipeline and the export action each
| half-own, read holistically against the one registry both sides share
| rather than re-implementing either side's own logic:
|
| 1. A real round trip for every kind the import pipeline can actually
|    write end to end (today, only "objects") — export a real record,
|    corrupt it, re-import the very artefact just produced, and prove the
|    original field values come back. A no-op update could pass a weaker
|    "no error" check while silently never writing a column the exporter
|    emits; corrupting first is what catches that.
| 2. For every kind the import pipeline does NOT yet write, the narrower,
|    honest claim: its wired export path (if any) still names only columns
|    its own registry entry declares — re-reading the registry to confirm
|    nothing has drifted since, not re-deriving the column list.
| 3. Zero automatic merges anywhere the import pipeline writes, swept over
|    every kind it wires rather than asserted once for "objects" alone.
| 4. Zero unpermitted columns in any wired export, swept over every kind
|    carrying a personal-data or financial column, reading the same
|    narrowing mechanism the export action itself calls.
|
*/

/** @return array{ownerId: int, countryId: int, territoryId: int, typeId: int} */
function dataTransferInvariantScope(): array
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

    return [
        'ownerId' => $ownerId,
        'countryId' => $countryId,
        'territoryId' => $territoryId,
        'typeId' => $typeId,
    ];
}

/** @param  list<string>  $permissions */
function dataTransferInvariantActor(string $roleKey, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

function dataTransferInvariantStreamContent(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/**
 * Discovers every `*Exporter.php` file under `app/Filament` and resolves it
 * to the registry key it reads — the same directory-walk-by-path
 * construction the registry's own containment sweep already established
 * (`tests/Unit/DataTransfer/TransferableRegistryTest.php`), reused here so
 * a future exporter is picked up automatically instead of needing this file
 * hand-updated to know it exists.
 *
 * @return array<string, class-string<Exporter>>
 */
function dataTransferInvariantDiscoverExporters(): array
{
    $map = [];

    $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Filament')));

    foreach ($directory as $fileInfo) {
        if (! $fileInfo->isFile() || ! str_ends_with($fileInfo->getFilename(), 'Exporter.php')) {
            continue;
        }

        $fqcn = exporterClassFromPath($fileInfo->getPathname());

        if (! class_exists($fqcn) || ! is_subclass_of($fqcn, Exporter::class) || ! method_exists($fqcn, 'transferableKey')) {
            continue;
        }

        /** @var class-string<Exporter> $fqcn */
        $map[$fqcn::transferableKey()] = $fqcn;
    }

    return $map;
}

/*
|--------------------------------------------------------------------------
| 1. Real Round Trip — "objects"
|--------------------------------------------------------------------------
*/

it('round-trips a real object through export and re-import, restoring exact field values a corrupting write clobbered in between', function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    $scope = dataTransferInvariantScope();
    $actor = dataTransferInvariantActor('objects_round_trip', [
        'admin_panel_access', 'object.view', 'object.export', 'object.create',
    ]);

    $ulid = (string) Str::ulid();
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => $ulid,
        'owner_id' => $scope['ownerId'],
        'object_type_id' => $scope['typeId'],
        'territory_id' => $scope['territoryId'],
        'country_id' => $scope['countryId'],
        'address' => 'Villa Fixture Original Address',
        'latitude' => 47.0105678,
        'longitude' => 28.8638123,
        'status' => 'published',
        'availability_status' => 'available',
        'moderation_status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The real header ExportAction, exactly as an operator would trigger it.
    Livewire::actingAs($actor)->test(ListObjects::class)->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
    expect($export->completed_at)->not->toBeNull()
        ->and($export->total_rows)->toBe(1);

    $csv = dataTransferInvariantStreamContent(app(CsvDownloader::class)($export));

    // Corrupt the very row the artefact just captured. A no-op re-import of
    // an already-correct row would pass even if the exporter silently
    // dropped a column the importer never re-applies; corrupting first
    // forces the re-import to prove it actually restores every field.
    DB::table('objects')->where('id', $objectId)->update([
        'address' => 'CORRUPTED', 'latitude' => 0, 'longitude' => 0,
        'status' => 'hidden', 'availability_status' => 'unavailable',
        'updated_at' => now(),
    ]);

    $file = UploadedFile::fake()->createWithContent('objects-export.csv', $csv);

    $component = Livewire::actingAs($actor)
        ->test(DataImport::class)
        ->set('kind', 'objects')
        ->set('file', $file)
        ->call('parseFile')
        ->assertHasNoErrors()
        ->assertSet('step', 'mapping');

    // The re-exported header row (registry labels, not raw column names)
    // auto-maps onto the registry's own columns with no operator
    // correction needed — a real export/import contract, not an assumed
    // one.
    expect($component->get('columnMap'))->toMatchArray([
        'id' => 'ID',
        'ulid' => 'ULID',
        'owner_id' => 'Owner ID',
        'object_type_id' => 'Object type ID',
        'territory_id' => 'Territory ID',
        'country_id' => 'Country ID',
        'address' => 'Address',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'status' => 'Status',
        'availability_status' => 'Availability status',
    ]);

    $component->call('runPreview')->assertSet('step', 'preview');
    expect($component->get('previewSummary'))->toMatchArray(['total' => 1, 'create' => 0, 'update' => 1, 'errors' => 0]);

    $component->call('confirmImport')->assertSet('step', 'done');

    $restored = DB::table('objects')->where('id', $objectId)->first();
    expect($restored)->not->toBeNull()
        ->and($restored->ulid)->toBe($ulid)
        ->and($restored->address)->toBe('Villa Fixture Original Address')
        ->and((string) $restored->latitude)->toBe('47.0105678')
        ->and((string) $restored->longitude)->toBe('28.8638123')
        ->and($restored->status)->toBe('published')
        ->and($restored->availability_status)->toBe('available')
        ->and((int) $restored->owner_id)->toBe($scope['ownerId'])
        ->and((int) $restored->object_type_id)->toBe($scope['typeId'])
        ->and((int) $restored->territory_id)->toBe($scope['territoryId'])
        ->and((int) $restored->country_id)->toBe($scope['countryId']);
});

/*
|--------------------------------------------------------------------------
| 2. Zero Automatic Merges — swept across every wired import kind
|--------------------------------------------------------------------------
*/

it('never merges automatically anywhere the import pipeline writes, across every kind it currently wires', function (): void {
    $scope = dataTransferInvariantScope();

    // One scenario per kind ImportKindRegistry actually wires. A kind added
    // there without a matching entry here fails the assertion below rather
    // than silently shipping an untested merge invariant.
    $scenarios = [
        'objects' => function () use ($scope): void {
            $existingUlid = (string) Str::ulid();
            DB::table('objects')->insertGetId([
                'ulid' => $existingUlid,
                'owner_id' => $scope['ownerId'], 'object_type_id' => $scope['typeId'],
                'territory_id' => $scope['territoryId'], 'country_id' => $scope['countryId'],
                'latitude' => 47.0105678, 'longitude' => 28.8638123,
                'status' => 'published', 'moderation_status' => 'approved',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $incomingUlid = (string) Str::ulid();
            $row = [
                'ulid' => $incomingUlid,
                'owner_id' => (string) $scope['ownerId'],
                'object_type_id' => (string) $scope['typeId'],
                'territory_id' => (string) $scope['territoryId'],
                'country_id' => (string) $scope['countryId'],
                // ~1 metre from the existing row above — well inside the
                // duplicate detector's 100 m coordinate radius.
                'latitude' => '47.010568', 'longitude' => '28.863813',
            ];
            $columnMap = [
                'id' => null, 'ulid' => 'ulid', 'owner_id' => 'owner_id',
                'object_type_id' => 'object_type_id', 'territory_id' => 'territory_id',
                'country_id' => 'country_id', 'address' => null,
                'latitude' => 'latitude', 'longitude' => 'longitude',
                'status' => null, 'availability_status' => null,
            ];

            $preview = app(ImportPipelineService::class)->preview('objects', [$row], $columnMap);
            expect($preview->duplicates)->toHaveCount(1);

            $actor = User::factory()->create();
            app(ImportPipelineService::class)->confirm('objects', [$row], $columnMap, $actor, 'objects.csv', 'imports/objects.csv');

            // A flagged candidate stays exactly that — a candidate. Both
            // rows exist, untouched, and nothing was ever redirected.
            expect(Object_::query()->withUnmoderated()->count())->toBe(2)
                ->and(DB::table('objects')->where('ulid', $existingUlid)->exists())->toBeTrue()
                ->and(DB::table('objects')->where('ulid', $incomingUlid)->exists())->toBeTrue()
                ->and(DB::table('redirects')->count())->toBe(0);
        },
    ];

    foreach (array_keys(ImportKindRegistry::all()) as $kind) {
        expect(array_key_exists($kind, $scenarios))
            ->toBeTrue("Kind [{$kind}] is wired for import but carries no merge-invariant scenario here — add one before shipping.");

        $scenarios[$kind]();
    }
});

/*
|--------------------------------------------------------------------------
| 3. Zero Unpermitted Columns — swept across every kind with a sensitive
|    column and a wired export path
|--------------------------------------------------------------------------
*/

it('never lets a wired export path emit a personal-data or financial column beyond what the acting permissions cover, across every kind carrying one', function (): void {
    $exportersByKind = dataTransferInvariantDiscoverExporters();

    $restricted = dataTransferInvariantActor('sweep_restricted', ['admin_panel_access']);
    $privileged = dataTransferInvariantActor('sweep_privileged', ['admin_panel_access', 'personal_data_access', 'financial_access']);

    $checkedKinds = [];

    foreach (TransferableRegistry::all() as $kind => $transferable) {
        $sensitive = array_merge($transferable->personalDataColumnNames(), $transferable->financialColumnNames());

        if ($sensitive === []) {
            continue;
        }

        if (! array_key_exists($kind, $exportersByKind)) {
            // No export path exists for this kind at all — the strongest
            // possible instance of "zero unpermitted columns", not a gap
            // this sweep silently ignores (see the honest-boundary
            // assertion in the following test).
            continue;
        }

        $exporterClass = $exportersByKind[$kind];
        $checkedKinds[] = $kind;

        $this->actingAs($restricted);
        $narrowedNames = array_map(static fn ($column): string => $column->getName(), $exporterClass::getVisibleColumns());

        foreach ($sensitive as $name) {
            expect($narrowedNames)->not->toContain($name);
        }

        $this->actingAs($privileged);
        $fullNames = array_map(static fn ($column): string => $column->getName(), $exporterClass::getVisibleColumns());

        foreach ($sensitive as $name) {
            expect($fullNames)->toContain($name);
        }
    }

    // Fails loudly if a kind carrying a sensitive column gains (or loses) a
    // wired exporter without this sweep being touched.
    expect($checkedKinds)->toEqualCanonicalizing(['owners', 'packages', 'payments', 'action_journal']);
});

/*
|--------------------------------------------------------------------------
| 4. Faithful Export — every export-only kind still reflects its own
|    registry entry, re-read rather than re-derived
|--------------------------------------------------------------------------
*/

it('keeps every wired export path naming only columns its own registry entry declares, across every kind the registry lists', function (): void {
    $exportersByKind = dataTransferInvariantDiscoverExporters();

    $violations = [];
    $unwiredKinds = [];

    foreach (TransferableRegistry::all() as $kind => $transferable) {
        if ($kind === 'objects') {
            continue; // The round trip above is a strictly stronger claim than column-name containment.
        }

        if (! array_key_exists($kind, $exportersByKind)) {
            $unwiredKinds[] = $kind;

            continue;
        }

        $exporterClass = $exportersByKind[$kind];
        $declared = $transferable->columnNames();

        foreach ($exporterClass::getColumns() as $column) {
            $name = $column->getName();

            if (str_contains($name, '.')) {
                continue; // A related record's own display attribute, not this entity's own column.
            }

            if (! in_array($name, $declared, true)) {
                $violations[] = "{$exporterClass} names column [{$name}], absent from the [{$kind}] registry entry.";
            }
        }
    }

    expect($violations)->toBe([]);

    // The three kinds the registry declares but no admin resource yet
    // administers — confirmed absent here, not merely assumed, so wiring
    // one of these in a future task is caught by this same sweep the
    // moment its own exporter starts naming columns.
    expect($unwiredKinds)->toEqualCanonicalizing(['contacts', 'prices', 'services']);
});
