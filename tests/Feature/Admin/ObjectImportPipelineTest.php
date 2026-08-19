<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\DataImport;
use App\Models\Object_;
use App\Models\User;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Object Import Pipeline
|--------------------------------------------------------------------------
|
| The pipeline's own claim is that validation and the write are two
| genuinely separate passes: a malformed row must never reach the objects
| table, and the preview's counts must match exactly what confirming the
| import actually writes. A real XLSX fixture drives every stage — upload,
| column mapping, validation, preview, confirm — through the real Livewire
| page, and the report is read back through fresh queries rather than the
| importing call's own return values, since an operator who closed the tab
| has nothing else to read it with.
|
*/

/** @return array{owner: User, countryId: int, territoryId: int, typeId: int, existingId: int, existingUlid: string} */
function objectImportFixture(): array
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
    $owner = User::factory()->create();

    $existingUlid = (string) Str::ulid();
    $existingId = DB::table('objects')->insertGetId([
        'ulid' => $existingUlid, 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'address' => 'Old Address', 'status' => 'draft', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'owner' => $owner,
        'countryId' => $countryId,
        'territoryId' => $territoryId,
        'typeId' => $typeId,
        'existingId' => $existingId,
        'existingUlid' => $existingUlid,
    ];
}

function importOperator(array $permissions = ['admin_panel_access', 'object.create']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('import_operator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/**
 * Writes a real XLSX file through openspout — the same library the import
 * pipeline itself reads with — so the fixture is genuine binary content, not
 * a mocked upload.
 *
 * @param  list<string>  $headers
 * @param  list<array<string, string>>  $rows
 */
function objectsFixtureXlsx(array $headers, array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'object-import-').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($headers));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues(array_map(
            static fn (string $header): string => $row[$header] ?? '',
            $headers,
        )));
    }

    $writer->close();

    $content = (string) file_get_contents($path);
    @unlink($path);

    return UploadedFile::fake()->createWithContent('objects.xlsx', $content);
}

it('validates and previews an objects import without writing anything, then confirms and reports it after the fact', function (): void {
    $fixture = objectImportFixture();
    $actor = importOperator();

    $headers = ['ulid', 'owner_id', 'object_type_id', 'territory_id', 'country_id', 'address', 'status'];
    $newUlid = (string) Str::ulid();

    $rows = [
        // Creates a new object.
        [
            'ulid' => $newUlid,
            'owner_id' => (string) $fixture['owner']->id,
            'object_type_id' => (string) $fixture['typeId'],
            'territory_id' => (string) $fixture['territoryId'],
            'country_id' => (string) $fixture['countryId'],
            'address' => 'New Villa Address',
            'status' => 'draft',
        ],
        // Updates the pre-existing object, matched by its ulid.
        [
            'ulid' => $fixture['existingUlid'],
            'owner_id' => (string) $fixture['owner']->id,
            'object_type_id' => (string) $fixture['typeId'],
            'territory_id' => (string) $fixture['territoryId'],
            'country_id' => (string) $fixture['countryId'],
            'address' => 'Updated Address',
            'status' => 'published',
        ],
        // Deliberately malformed: object_type_id names a row that does not exist.
        [
            'ulid' => (string) Str::ulid(),
            'owner_id' => (string) $fixture['owner']->id,
            'object_type_id' => '999999',
            'territory_id' => (string) $fixture['territoryId'],
            'country_id' => (string) $fixture['countryId'],
            'address' => 'Malformed Row',
            'status' => 'draft',
        ],
    ];

    $file = objectsFixtureXlsx($headers, $rows);

    $component = Livewire::actingAs($actor)
        ->test(DataImport::class)
        ->set('kind', 'objects')
        ->set('file', $file)
        ->call('parseFile')
        ->assertHasNoErrors()
        ->assertSet('step', 'mapping');

    // The uploaded headers match the registry's own column names exactly,
    // so the auto-guessed map should need no operator correction.
    expect($component->get('columnMap'))->toMatchArray([
        'ulid' => 'ulid',
        'owner_id' => 'owner_id',
        'object_type_id' => 'object_type_id',
        'territory_id' => 'territory_id',
        'country_id' => 'country_id',
        'address' => 'address',
        'status' => 'status',
    ]);

    $component->call('runPreview')->assertSet('step', 'preview');

    // Partial match: this test's own concern is validation/preview/confirm
    // parity, not duplicate detection — the 'duplicates' key it now also
    // carries is exercised by its own dedicated test.
    expect($component->get('previewSummary'))->toMatchArray(['total' => 3, 'create' => 1, 'update' => 1, 'errors' => 1]);

    $errors = $component->get('previewErrors');
    expect($errors)->toHaveCount(1)
        ->and($errors[0]['row'])->toBe(3);

    // The validation pass wrote nothing: the pre-existing row is untouched
    // and neither of the other two rows exists yet.
    expect(Object_::query()->withUnmoderated()->count())->toBe(1)
        ->and(Object_::query()->withUnmoderated()->find($fixture['existingId'])->address)->toBe('Old Address')
        ->and(DB::table('objects')->where('ulid', $newUlid)->exists())->toBeFalse();

    $component->call('confirmImport')->assertSet('step', 'done');

    $importId = $component->get('importId');
    expect($importId)->not->toBeNull();

    // Fresh queries from here on — a later, unrelated request's own read,
    // not this test's in-memory state left over from the import call.
    $import = Import::query()->findOrFail($importId);
    expect($import->total_rows)->toBe(3)
        ->and($import->successful_rows)->toBe(2)
        ->and($import->getFailedRowsCount())->toBe(1)
        ->and($import->completed_at)->not->toBeNull();

    $failedRows = FailedImportRow::query()->where('import_id', $importId)->get();
    expect($failedRows)->toHaveCount(1);

    expect(Object_::query()->withUnmoderated()->count())->toBe(2)
        ->and(DB::table('objects')->where('object_type_id', 999999)->exists())->toBeFalse();

    $created = Object_::query()->withUnmoderated()->where('ulid', $newUlid)->first();
    expect($created)->not->toBeNull()
        ->and($created->address)->toBe('New Villa Address')
        ->and($created->status)->toBe('draft');

    $updated = Object_::query()->withUnmoderated()->find($fixture['existingId']);
    expect($updated->address)->toBe('Updated Address')
        ->and($updated->status)->toBe('published');
});

it('refuses the import screen to a staff account without object.create', function (): void {
    $user = importOperator(['admin_panel_access']);

    $this->actingAs($user)->get(DataImport::getUrl(panel: 'admin'))->assertForbidden();
});
