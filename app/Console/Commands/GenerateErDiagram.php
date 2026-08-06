<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Regenerates `docs/database-schema.md` from the schema actually applied to
 * the database — introspected via `information_schema`, never hand-drawn —
 * so the diagram cannot drift from a migration the way a document
 * maintained by hand would the first time someone forgets to update it.
 */
final class GenerateErDiagram extends Command
{
    protected $signature = 'schema:er-diagram';

    protected $description = 'Regenerate docs/database-schema.md from the applied database schema';

    public function handle(): int
    {
        $tables = $this->fetchTables();
        $columns = $this->fetchColumns();
        $primaryKeys = $this->fetchPrimaryKeys();
        $foreignKeys = $this->fetchForeignKeys();

        $lines = ['erDiagram'];

        foreach ($tables as $table) {
            $lines[] = "    {$table} {";

            /** @var list<object{column_name: string, data_type: string}> $tableColumns */
            $tableColumns = $columns[$table] ?? [];

            foreach ($tableColumns as $column) {
                $type = $this->mermaidType($column->data_type);
                $key = in_array($column->column_name, $primaryKeys[$table] ?? [], true) ? ' PK' : '';
                $lines[] = "        {$type} {$column->column_name}{$key}";
            }

            $lines[] = '    }';
        }

        foreach ($foreignKeys as $foreignKey) {
            $lines[] = sprintf(
                '    %s ||--o{ %s : "%s"',
                $foreignKey->foreign_table,
                $foreignKey->table_name,
                $foreignKey->column_name
            );
        }

        $content = $this->renderDocument(implode("\n", $lines), count($tables), count($foreignKeys));

        File::put(base_path('docs/database-schema.md'), $content);

        $this->info(sprintf('Wrote docs/database-schema.md — %d tables, %d foreign keys.', count($tables), count($foreignKeys)));

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function fetchTables(): array
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();
    }

    /**
     * @return array<string, array<int, \stdClass>> keyed by table name; each
     *                                              row carries `column_name` and `data_type` (asserted at the call
     *                                              site, since Laravel's collection generics cannot express that shape
     *                                              through this method's own return type).
     */
    private function fetchColumns(): array
    {
        $rows = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->orderBy('table_name')
            ->orderBy('ordinal_position')
            ->get(['table_name', 'column_name', 'data_type']);

        return $rows->groupBy('table_name')->map(fn ($group) => $group->all())->all();
    }

    /** @return array<string, list<string>> */
    private function fetchPrimaryKeys(): array
    {
        $rows = DB::select(<<<'SQL'
            select tc.table_name, kcu.column_name
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
                on tc.constraint_name = kcu.constraint_name and tc.table_schema = kcu.table_schema
            where tc.constraint_type = 'PRIMARY KEY' and tc.table_schema = 'public'
            SQL);

        $byTable = [];
        foreach ($rows as $row) {
            $byTable[$row->table_name][] = $row->column_name;
        }

        return $byTable;
    }

    /** @return array<int, object{table_name: string, column_name: string, foreign_table: string}> */
    private function fetchForeignKeys(): array
    {
        return DB::select(<<<'SQL'
            select
                tc.table_name,
                kcu.column_name,
                ccu.table_name as foreign_table
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
                on tc.constraint_name = kcu.constraint_name and tc.table_schema = kcu.table_schema
            join information_schema.constraint_column_usage ccu
                on tc.constraint_name = ccu.constraint_name and tc.table_schema = ccu.table_schema
            where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public'
            order by tc.table_name, kcu.column_name
            SQL);
    }

    /**
     * Mermaid's `erDiagram` column-type token accepts no spaces or
     * parentheses — Postgres types like `character varying` or
     * `timestamp without time zone` are collapsed to a single word.
     */
    private function mermaidType(string $postgresType): string
    {
        return match (true) {
            str_contains($postgresType, 'timestamp') => 'timestamp',
            str_contains($postgresType, 'character') => 'string',
            default => str_replace(' ', '_', $postgresType),
        };
    }

    private function renderDocument(string $mermaid, int $tableCount, int $foreignKeyCount): string
    {
        $generatedAt = now()->toDateTimeString();

        return <<<MARKDOWN
            # Database Schema

            Generated from the applied schema by `php artisan schema:er-diagram` — never
            hand-edited. Regenerate after any migration change:

            ```
            php artisan schema:er-diagram
            ```

            Generated at {$generatedAt} · {$tableCount} tables · {$foreignKeyCount} foreign keys.

            ```mermaid
            {$mermaid}
            ```

            MARKDOWN;
    }
}
