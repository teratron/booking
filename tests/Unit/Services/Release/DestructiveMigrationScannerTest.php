<?php

declare(strict_types=1);

use App\Services\Release\DestructiveMigrationScanner;

/*
|--------------------------------------------------------------------------
| Destructive Migration Scanner
|--------------------------------------------------------------------------
|
| Pure text analysis, no database and no git — the command wrapping this
| service owns both of those. Fixtures are written to a temporary
| directory per test and cleaned up afterward, never touching the real
| database/migrations tree.
|
*/

beforeEach(function (): void {
    $this->fixtureDir = sys_get_temp_dir().'/destructive-migration-scanner-test-'.bin2hex(random_bytes(8));
    mkdir($this->fixtureDir);
});

afterEach(function (): void {
    foreach (glob($this->fixtureDir.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->fixtureDir);
});

function writeMigrationFixture(string $dir, string $name, string $body): string
{
    $path = "{$dir}/{$name}.php";
    file_put_contents($path, "<?php\n\nreturn new class {\n    public function up(): void\n    {\n{$body}\n    }\n};\n");

    return $path;
}

test('flags a dropped table', function (): void {
    $file = writeMigrationFixture($this->fixtureDir, 'drop_table', "        Schema::dropIfExists('legacy_bookings');");

    $findings = (new DestructiveMigrationScanner)->scan([$file]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->file)->toBe($file)
        ->and($findings[0]->description)->toContain('drops a table');
});

test('flags a dropped column', function (): void {
    $file = writeMigrationFixture($this->fixtureDir, 'drop_column', "        Schema::table('objects', function (\$table) {\n            \$table->dropColumn('legacy_field');\n        });");

    $findings = (new DestructiveMigrationScanner)->scan([$file]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->description)->toContain('drops one or more columns');
});

test('flags a dropped constraint', function (): void {
    $file = writeMigrationFixture($this->fixtureDir, 'drop_constraint', "        Schema::table('objects', function (\$table) {\n            \$table->dropForeign(['owner_id']);\n        });");

    $findings = (new DestructiveMigrationScanner)->scan([$file]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->description)->toContain('drops a foreign key constraint');
});

test('flags a raw SQL drop', function (): void {
    $file = writeMigrationFixture($this->fixtureDir, 'raw_drop', "        DB::statement('ALTER TABLE objects DROP COLUMN legacy_field');");

    $findings = (new DestructiveMigrationScanner)->scan([$file]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->description)->toContain('raw SQL drops');
});

test('does not flag a create-table migration whose down() reverses it with dropIfExists', function (): void {
    $file = writeMigrationFixture(
        $this->fixtureDir,
        'create_table',
        <<<'PHP'
                Schema::create('widgets', function ($table) {
                    $table->id();
                });
                PHP
    );

    // writeMigrationFixture() only writes up() — append a realistic down()
    // by hand, since this is precisely the shape the scanner must ignore.
    file_put_contents($file, str_replace(
        "};\n",
        <<<'PHP'

                    public function down(): void
                    {
                        Schema::dropIfExists('widgets');
                    }
                };

                PHP,
        (string) file_get_contents($file)
    ));

    expect((new DestructiveMigrationScanner)->scan([$file]))->toBe([]);
});

test('finds nothing in an additive migration', function (): void {
    $file = writeMigrationFixture($this->fixtureDir, 'add_column', "        Schema::table('objects', function (\$table) {\n            \$table->string('new_field')->nullable();\n        });");

    expect((new DestructiveMigrationScanner)->scan([$file]))->toBe([]);
});

test('ignores a path that does not exist', function (): void {
    expect((new DestructiveMigrationScanner)->scan([$this->fixtureDir.'/does_not_exist.php']))->toBe([]);
});

test('recognises an explicit irreversibility declaration, case-insensitively and anywhere in the message', function (): void {
    $scanner = new DestructiveMigrationScanner;

    expect($scanner->declaresIrreversible("Fixes a typo.\n\nIrreversible: drops the deprecated legacy_bookings table."))->toBeTrue()
        ->and($scanner->declaresIrreversible("release notes\nIRREVERSIBLE: same reason, different case"))->toBeTrue()
        ->and($scanner->declaresIrreversible('Just a routine release, nothing destructive.'))->toBeFalse()
        ->and($scanner->declaresIrreversible(''))->toBeFalse();
});
