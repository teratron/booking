<?php

declare(strict_types=1);

namespace App\Services\Release;

/**
 * Scans a set of migration files for operations a plain rollback — pinning
 * back to the previous release's artefact and restarting — cannot undo: a
 * dropped table, a dropped column, a removed constraint data depends on,
 * or an existing column's changed definition. Whether a schema change can
 * be reversed by redeploying the previous release must be known before
 * that release ships, never discovered during an incident.
 *
 * Deliberately noisy in one direction. Every `->change()` call is flagged
 * regardless of whether the change actually narrows anything, because
 * telling a narrowing change from a widening one requires comparing
 * against the column's previous definition — invisible to a scanner
 * reading one migration file in isolation. A false positive costs one
 * sentence in a release's irreversibility declaration; a false negative
 * costs the restore path during an outage. Requiring every migration to
 * define its own reversal instead was considered and rejected: that rule
 * is routinely satisfied by writing a reversal nobody has ever executed,
 * which produces false confidence in an untested path — worse than an
 * honest declaration of irreversibility.
 */
final class DestructiveMigrationScanner
{
    /**
     * @var array<string, string> regex pattern => plain-language description
     *                            of the operation it matches
     */
    private const array PATTERNS = [
        '/Schema::drop(?:IfExists)?\s*\(/' => 'drops a table',
        '/->dropColumn\s*\(/' => 'drops one or more columns',
        '/->dropForeign\s*\(/' => 'drops a foreign key constraint',
        '/->dropUnique\s*\(/' => 'drops a unique constraint',
        '/->dropIndex\s*\(/' => 'drops an index',
        '/->dropPrimary\s*\(/' => 'drops a primary key',
        '/->change\s*\(\s*\)/' => "changes an existing column's definition (may narrow its type)",
        '/DB::statement\s*\([^;]*?\bDROP\s+(?:TABLE|COLUMN)\b/is' => 'raw SQL drops a table or column',
    ];

    /**
     * @param  list<string>  $migrationFiles  repo-relative or absolute paths
     * @return list<DestructiveMigrationFinding>
     */
    public function scan(array $migrationFiles): array
    {
        $findings = [];

        foreach ($migrationFiles as $file) {
            if (! is_file($file)) {
                continue;
            }

            $upBody = $this->upMethodBody((string) file_get_contents($file));

            foreach (self::PATTERNS as $pattern => $description) {
                if (preg_match($pattern, $upBody) === 1) {
                    $findings[] = new DestructiveMigrationFinding($file, $description);
                }
            }
        }

        return $findings;
    }

    /**
     * Every Laravel migration's own `down()` legitimately calls
     * `Schema::dropIfExists()` to reverse its `up()`'s `Schema::create()` —
     * the exact pattern this scanner exists to flag when it appears in
     * `up()`. Scanning the whole file rather than only `up()`'s body would
     * flag every single `create_*_table` migration in the repository as
     * destructive, which is not a false positive this scanner's own
     * noisy-by-design posture is meant to tolerate — it is simply wrong.
     * `up()` precedes `down()` in every migration this codebase writes
     * (Laravel's own `make:migration` stub order), so splitting on the
     * first `down()` signature and keeping only what precedes it is a
     * reliable isolation without needing a full PHP parser to balance
     * braces around it.
     */
    private function upMethodBody(string $contents): string
    {
        $sections = preg_split('/\bpublic\s+function\s+down\s*\(/', $contents, 2);

        return $sections[0] ?? $contents;
    }

    /**
     * A tag annotation declares a release irreversible with an explicit,
     * greppable line — never inferred from surrounding prose, so a human
     * reviewing the release record later can find the statement without
     * reading the whole message. Case-insensitive: `git tag -a` messages
     * are free text.
     */
    public function declaresIrreversible(string $tagAnnotation): bool
    {
        return preg_match('/^\s*irreversible\s*:/mi', $tagAnnotation) === 1;
    }
}
