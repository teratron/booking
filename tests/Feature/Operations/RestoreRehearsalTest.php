<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\DatabaseRestoreService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\Backup;

/*
|--------------------------------------------------------------------------
| Rehearsed Restore
|--------------------------------------------------------------------------
|
| A rehearsed restore, not merely a documented one: a real artefact,
| produced by the same App\Services\Backup\DatabaseBackupService the daily
| schedule calls, replayed by the same App\Services\Backup\
| DatabaseRestoreService the re-authenticated restore screen calls, into a
| database dropped to genuinely empty first.
|
| This file deliberately does NOT declare RefreshDatabase. Every other
| Feature test in this suite shares the one 'booking_testing' database that
| trait wraps in a per-test transaction — but DatabaseRestoreService::
| restore() drops and recreates an entire 'public' schema via a raw `psql`
| subprocess entirely outside any Laravel transaction (see that service's
| own docblock). Running that for real against 'booking_testing' would
| permanently destroy it for every test that runs afterward in the same
| process — precisely the class of incident this project's own operational
| history already records once. Instead, this test provisions a third,
| genuinely disposable database ('booking_restore_rehearsal'), migrates and
| seeds it, backs it up for real, drops it to empty, restores it, and
| asserts parity — then drops that database again in a `finally` block,
| pass or fail, so a crashed run never leaves an orphan behind for the next
| one. No row here is ever written to 'booking' or 'booking_testing'.
|
*/

function restoreRehearsalDatabaseName(): string
{
    return 'booking_restore_rehearsal';
}

function restoreRehearsalConnectionName(): string
{
    return 'rehearsal';
}

function restoreRehearsalMaintenancePdo(string $database): PDO
{
    /** @var array<string, mixed> $base */
    $base = (array) config('database.connections.pgsql');

    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', (string) $base['host'], (string) $base['port'], $database),
        (string) $base['username'],
        (string) $base['password'],
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

/**
 * Drops (if present) and recreates the rehearsal database from scratch via
 * a maintenance connection to Postgres's own 'postgres' database — CREATE
 * DATABASE/DROP DATABASE cannot run inside a transaction or against the
 * database they target. Existing backends are terminated first so a
 * database orphaned by a previously aborted run does not block the drop.
 * The same three extensions docker/postgres/init/*.sql enables on the
 * development and test databases are enabled here too — the application's
 * own schema will not create without them.
 */
function restoreRehearsalRecreateDatabase(): void
{
    $name = restoreRehearsalDatabaseName();
    $maintenance = restoreRehearsalMaintenancePdo('postgres');

    $maintenance->exec(sprintf(
        'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = %s AND pid <> pg_backend_pid()',
        $maintenance->quote($name),
    ));
    // The database name is a fixed literal this test controls, never user
    // input — CREATE/DROP DATABASE do not accept bound parameters, so a
    // literal identifier is the only way to address them at all.
    $maintenance->exec("DROP DATABASE IF EXISTS {$name}");
    $maintenance->exec("CREATE DATABASE {$name}");

    $fresh = restoreRehearsalMaintenancePdo($name);
    $fresh->exec('CREATE EXTENSION IF NOT EXISTS postgis');
    $fresh->exec('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    $fresh->exec('CREATE EXTENSION IF NOT EXISTS unaccent');
}

/** Closes this test's own connection first, then drops the database for real. */
function restoreRehearsalDropDatabase(): void
{
    $name = restoreRehearsalDatabaseName();

    // Postgres refuses to drop a database any session — including this
    // test's own Eloquent/DB connection — still holds open.
    DB::purge(restoreRehearsalConnectionName());

    $maintenance = restoreRehearsalMaintenancePdo('postgres');
    $maintenance->exec(sprintf(
        'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = %s AND pid <> pg_backend_pid()',
        $maintenance->quote($name),
    ));
    $maintenance->exec("DROP DATABASE IF EXISTS {$name}");
}

/** Registers a throwaway Laravel connection pointed at the rehearsal database, same host/port/credentials as 'pgsql'. */
function restoreRehearsalRegisterConnection(): void
{
    /** @var array<string, mixed> $base */
    $base = (array) config('database.connections.pgsql');
    $base['database'] = restoreRehearsalDatabaseName();

    config(['database.connections.'.restoreRehearsalConnectionName() => $base]);
    DB::purge(restoreRehearsalConnectionName());
}

/**
 * Seeds just enough registries and rows for the parity assertions to be
 * meaningful — three objects with English translations, one of which
 * carries two media rows and a second one carrying a third — not
 * DemoVolumeSeeder's full launch-scale volume, which this rehearsal has no
 * need of.
 *
 * @return array{sampleObjectId: int, sampleTranslationId: int, sampleMediaId: int}
 */
function restoreRehearsalSeed(): array
{
    $now = now();

    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'is_active' => true,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $ownerId = User::factory()->create()->id;

    $objectIds = [];

    foreach (['Rehearsal Hotel Alpha', 'Rehearsal Hotel Beta', 'Rehearsal Hotel Gamma'] as $index => $name) {
        $object = Object_::create([
            'ulid' => (string) Str::ulid(),
            'owner_id' => $ownerId,
            'object_type_id' => $objectTypeId,
            'territory_id' => $territoryId,
            'country_id' => $countryId,
            'address' => "{$index} Rehearsal Street",
            'status' => 'published',
            'moderation_status' => 'approved',
        ]);

        DB::table('object_translations')->insert([
            'object_id' => $object->id,
            'locale' => 'en',
            'name' => $name,
            'short_description' => "Seeded for the restore rehearsal: {$name}.",
            'slug' => Str::slug($name),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $objectIds[] = $object->id;
    }

    $sampleObjectId = $objectIds[0];

    /** @var Object_ $sampleObject */
    $sampleObject = Object_::query()->findOrFail($sampleObjectId);
    $sampleObject->addMedia(UploadedFile::fake()->image('rehearsal-cover.jpg', 640, 480))->toMediaCollection('photos');
    $sampleObject->addMedia(UploadedFile::fake()->image('rehearsal-lobby.jpg', 640, 480))->toMediaCollection('photos');

    /** @var Object_ $secondObject */
    $secondObject = Object_::query()->findOrFail($objectIds[1]);
    $secondObject->addMedia(UploadedFile::fake()->image('rehearsal-room.jpg', 640, 480))->toMediaCollection('photos');

    $sampleTranslationId = (int) DB::table('object_translations')->where('object_id', $sampleObjectId)->value('id');
    $sampleMediaId = (int) DB::table('media')->where('model_id', $sampleObjectId)->orderBy('id')->value('id');

    return [
        'sampleObjectId' => $sampleObjectId,
        'sampleTranslationId' => $sampleTranslationId,
        'sampleMediaId' => $sampleMediaId,
    ];
}

/** @return array{objects: int, object_translations: int, media: int} */
function restoreRehearsalPrincipalTableCounts(): array
{
    return [
        'objects' => DB::table('objects')->count(),
        'object_translations' => DB::table('object_translations')->count(),
        'media' => DB::table('media')->count(),
    ];
}

it('rehearses a full backup-and-restore cycle against a disposable database, and asserts row-count and field-level parity', function (): void {
    $timings = [];
    $overallStart = microtime(true);

    $originalDefaultConnection = config('database.default');
    $originalSourceDatabases = config('backup.backup.source.databases');
    $originalBackupName = config('backup.backup.name');

    $backup = null;

    try {
        // 1. Provision a genuinely disposable third database — never
        // 'booking', never 'booking_testing' — and point both the
        // Eloquent default connection and the backup source at it for the
        // duration of this test only.
        //
        // The backup-source override is set here, before the very first
        // Artisan::call() of this test, not later next to the backup step
        // itself where it would read more naturally: Spatie\Backup\
        // Commands\BackupCommand constructor-injects a `scoped` Config
        // value object built once from config('backup') at resolution
        // time, and Laravel's console kernel resolves (and permanently
        // caches) every registered Artisan command, including this one,
        // the first time Artisan::call() runs in the process — the
        // migrate call below, if the override were not already in place
        // by then. A later config() write or even forgetInstance() on the
        // Config binding cannot reach an object that already captured its
        // own reference to the old value. Confirmed the hard way: the
        // rehearsal's first run backed up 'booking_testing' instead of
        // the rehearsal database until this ordering was fixed — see
        // docs/restore-rehearsal.md for the write-up.
        $step = microtime(true);
        restoreRehearsalRecreateDatabase();
        restoreRehearsalRegisterConnection();
        config([
            'database.default' => restoreRehearsalConnectionName(),
            'backup.backup.source.databases' => [restoreRehearsalConnectionName()],
            'backup.backup.name' => 'restore-rehearsal',
        ]);
        $timings['provision_database_seconds'] = round(microtime(true) - $step, 3);

        $step = microtime(true);
        Artisan::call('migrate', [
            '--database' => restoreRehearsalConnectionName(),
            '--force' => true,
        ]);
        $timings['migrate_seconds'] = round(microtime(true) - $step, 3);

        $step = microtime(true);
        $fixture = restoreRehearsalSeed();
        $timings['seed_seconds'] = round(microtime(true) - $step, 3);

        $expectedCounts = restoreRehearsalPrincipalTableCounts();
        // A realistic minimum, not merely "greater than zero" — proves the
        // seeded fixture actually populated all three principal tables
        // before backup, so a later "0 == 0" parity match could never pass
        // by coincidence.
        expect($expectedCounts['objects'])->toBe(3)
            ->and($expectedCounts['object_translations'])->toBe(3)
            ->and($expectedCounts['media'])->toBe(3);

        $seededObject = (array) DB::table('objects')->where('id', $fixture['sampleObjectId'])->first();
        $seededTranslation = (array) DB::table('object_translations')->where('id', $fixture['sampleTranslationId'])->first();
        $seededMedia = (array) DB::table('media')->where('id', $fixture['sampleMediaId'])->first();

        // 2. Back it up for real — the same service the daily schedule
        // calls, against the real 'backups' destination disk, integrity-
        // verified before it returns. Source connection and backup name
        // were already pointed at the rehearsal database in step 1 (see
        // the comment there for why that ordering is load-bearing) — the
        // artefact lives in its own 'restore-rehearsal' folder on that
        // disk, never mixed into the real generation-count retention
        // history.
        $step = microtime(true);
        /** @var Backup $backup */
        $backup = app(DatabaseBackupService::class)->run();
        $timings['backup_seconds'] = round(microtime(true) - $step, 3);

        expect($backup->exists())->toBeTrue();

        // 3. Drop the rehearsal database's own public schema to genuinely
        // empty — asserted, not assumed, before the restore runs.
        $step = microtime(true);
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');

        $remainingTables = (int) DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->count();
        $timings['drop_to_empty_seconds'] = round(microtime(true) - $step, 3);

        expect($remainingTables)->toBe(0);

        // 4. Restore for real — the same service the re-authenticated
        // administration screen calls.
        $step = microtime(true);
        app(DatabaseRestoreService::class)->restore($backup->path());
        $timings['restore_seconds'] = round(microtime(true) - $step, 3);

        // A fresh connection, not the one opened before the schema was
        // dropped and recreated out from under it by a separate `psql`
        // subprocess — belt and braces against any stale client-side state
        // surviving a catalog change made by another session.
        DB::purge(restoreRehearsalConnectionName());

        // 5. Assert the restored state matches what was backed up: row
        // counts across the principal tables, and a field-by-field
        // comparison of one sampled object with its translation and one of
        // its media rows.
        $restoredCounts = restoreRehearsalPrincipalTableCounts();
        expect($restoredCounts)->toBe($expectedCounts);

        $restoredObject = (array) DB::table('objects')->where('id', $fixture['sampleObjectId'])->first();
        $restoredTranslation = (array) DB::table('object_translations')->where('id', $fixture['sampleTranslationId'])->first();
        $restoredMedia = (array) DB::table('media')->where('id', $fixture['sampleMediaId'])->first();

        expect($restoredObject)->toEqual($seededObject)
            ->and($restoredTranslation)->toEqual($seededTranslation)
            ->and($restoredMedia)->toEqual($seededMedia);
    } finally {
        // Runs whether the assertions above passed or failed — a crashed
        // rehearsal must never leave an orphaned database or a stray
        // artefact behind for the next run.
        $backup?->delete();

        restoreRehearsalDropDatabase();

        config([
            'database.default' => $originalDefaultConnection,
            'backup.backup.source.databases' => $originalSourceDatabases,
            'backup.backup.name' => $originalBackupName,
        ]);
    }

    $timings['total_seconds'] = round(microtime(true) - $overallStart, 3);

    // Printed directly to the real terminal (not PHPUnit's own captured
    // output) so the actual, measured figures are visible in the command
    // that ran this test — the numbers docs/backups.md's restore-rehearsal
    // section records are read from exactly this line, not invented.
    fwrite(STDERR, "\n[restore rehearsal timings] ".json_encode($timings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    expect($timings['total_seconds'])->toBeGreaterThan(0.0);
})->group('slow');
