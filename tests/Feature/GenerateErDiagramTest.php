<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| ER Diagram Generation
|--------------------------------------------------------------------------
|
| Proves the diagram is derived from the schema, not hand-drawn: a table
| that genuinely exists in the database must appear in the regenerated
| file, and the file's own table count must match a real count query
| against `information_schema` rather than a hard-coded expectation.
|
*/

test('schema:er-diagram regenerates docs/database-schema.md from the applied schema', function (): void {
    $path = base_path('docs/database-schema.md');
    $originalContent = file_exists($path) ? file_get_contents($path) : null;

    try {
        $exitCode = Artisan::call('schema:er-diagram');

        expect($exitCode)->toBe(0);
        expect($path)->toBeFile();

        $content = file_get_contents($path);
        $realTableCount = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_type', 'BASE TABLE')
            ->count();

        expect($content)->toContain('```mermaid');
        expect($content)->toContain('erDiagram');
        // A table known to exist in the real schema, not an assumed name.
        expect($content)->toContain('objects {');
        expect($content)->toContain('countries {');
        expect($content)->toContain((string) $realTableCount.' tables');
    } finally {
        // Restore whatever was committed — this test's own run against the
        // testing database must not leave the repo's tracked diagram stale
        // relative to the development database it documents.
        if ($originalContent !== null) {
            file_put_contents($path, $originalContent);
        }
    }
});
