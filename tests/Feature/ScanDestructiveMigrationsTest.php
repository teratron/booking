<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

/*
|--------------------------------------------------------------------------
| release:scan-destructive-migrations
|--------------------------------------------------------------------------
|
| The command's own git calls are faked (Process::fake) so these assertions
| exercise the exit-code contract the two workflow steps depend on, without
| needing a real tagged repository. The scanning logic itself is covered
| directly against fixture files in Tests\Unit\Services\Release.
|
*/

test('fails when a destructive migration is not declared irreversible', function (): void {
    $migration = fixtureMigrationDroppingAColumn();

    Process::fake([
        'git diff*' => Process::result($migration."\n"),
    ]);

    $this->artisan('release:scan-destructive-migrations', ['--since' => 'fixture-baseline'])
        ->assertFailed();

    unlink($migration);
});

test('passes when the same destructive migration is declared irreversible', function (): void {
    $migration = fixtureMigrationDroppingAColumn();

    Process::fake([
        'git diff*' => Process::result($migration."\n"),
    ]);

    $this->artisan('release:scan-destructive-migrations', [
        '--since' => 'fixture-baseline',
        '--declaration' => "Irreversible: drops a deprecated column on purpose.\n",
    ])->assertSuccessful();

    unlink($migration);
});

test('does not block at review time in --advisory mode, even with no declaration', function (): void {
    $migration = fixtureMigrationDroppingAColumn();

    Process::fake([
        'git diff*' => Process::result($migration."\n"),
    ]);

    $this->artisan('release:scan-destructive-migrations', ['--since' => 'fixture-baseline', '--advisory' => true])
        ->assertSuccessful();

    unlink($migration);
});

test('passes with no findings against the range Verify names — the existing migration set has nothing destructive relative to itself', function (): void {
    Process::fake([
        'git diff*' => Process::result(''),
    ]);

    $this->artisan('release:scan-destructive-migrations', ['--since' => 'HEAD'])
        ->assertSuccessful();
});

test('reports no destructive operations when the diffed migrations are purely additive', function (): void {
    $migration = fixtureMigrationAddingAColumn();

    Process::fake([
        'git diff*' => Process::result($migration."\n"),
    ]);

    $this->artisan('release:scan-destructive-migrations', ['--since' => 'fixture-baseline'])
        ->expectsOutputToContain('no destructive operations found')
        ->assertSuccessful();

    unlink($migration);
});

test('resolves the baseline from the most recent v* tag when --since is omitted', function (): void {
    Process::fake([
        'git describe*' => Process::result("v1.2.3\n"),
        'git diff*' => Process::result(''),
    ]);

    // No new migrations against that resolved tag — the message naming it
    // is proof resolvePreviousTag() actually returned the faked tag rather
    // than falling through to the empty-tree default.
    $this->artisan('release:scan-destructive-migrations')
        ->expectsOutputToContain('No migrations introduced since v1.2.3.')
        ->assertSuccessful();
});

test('falls back to the empty-tree hash when no previous v* tag exists', function (): void {
    Process::fake([
        'git describe*' => Process::result('', 'fatal: no tags can describe', 1),
        'git diff*' => Process::result(''),
    ]);

    $this->artisan('release:scan-destructive-migrations')
        ->expectsOutputToContain('No migrations introduced since 4b825dc642cb6eb9a060e54bf8d69288fbee4904.')
        ->assertSuccessful();
});

function fixtureMigrationAddingAColumn(): string
{
    // Mirrors fixtureMigrationDroppingAColumn() below, but purely additive
    // — exercises the "files exist, but nothing in them is destructive"
    // branch, distinct from the "no files at all" branch the other tests
    // already cover via an empty git diff.
    $path = sys_get_temp_dir().'/fixture_adds_a_column_'.bin2hex(random_bytes(8)).'.php';

    file_put_contents($path, <<<'PHP'
        <?php

        return new class {
            public function up(): void
            {
                Schema::table('objects', function ($table) {
                    $table->string('new_field')->nullable();
                });
            }
        };
        PHP);

    return $path;
}

function fixtureMigrationDroppingAColumn(): string
{
    // A system temp path, deliberately outside database/migrations — the
    // command's own `is_file()` check works against whatever path the
    // (faked) git diff names, with no requirement that it sit in the real
    // migrations tree, so nothing here risks the migrator ever discovering
    // this fixture.
    $path = sys_get_temp_dir().'/fixture_drops_a_column_'.bin2hex(random_bytes(8)).'.php';

    file_put_contents($path, <<<'PHP'
        <?php

        return new class {
            public function up(): void
            {
                Schema::table('objects', function ($table) {
                    $table->dropColumn('legacy_field');
                });
            }
        };
        PHP);

    return $path;
}
