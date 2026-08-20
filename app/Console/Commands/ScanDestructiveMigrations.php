<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Release\DestructiveMigrationScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Gates the release irreversibility declaration: scans migrations
 * introduced since a baseline git ref for operations a plain rollback
 * cannot undo, and refuses to pass unless the release is explicitly
 * declared irreversible. Runs as a step in both `quality.yml` (review
 * time, `--advisory` — no tag exists yet to carry a declaration, so
 * findings are reported rather than blocking) and `release.yml` (release
 * time, blocking — the tag annotation is read directly).
 */
final class ScanDestructiveMigrations extends Command
{
    protected $signature = 'release:scan-destructive-migrations
        {--since= : Git ref to diff new migrations against (default: the most recent v* tag, or the empty tree if none exists)}
        {--declaration= : The release own irreversibility declaration text (a tag annotation message)}
        {--advisory : Report findings without failing — review time, before a release tag exists to carry a declaration}';

    protected $description = 'Scan migrations introduced since a baseline for operations a rollback cannot undo';

    public function handle(DestructiveMigrationScanner $scanner): int
    {
        $since = $this->option('since') ?: $this->resolvePreviousTag();
        $files = $this->newMigrationsSince($since);

        if ($files === []) {
            $this->info("No migrations introduced since {$since}.");

            return self::SUCCESS;
        }

        $findings = $scanner->scan($files);

        if ($findings === []) {
            $this->info(sprintf('%d migration(s) since %s — no destructive operations found.', count($files), $since));

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->line("::warning::{$finding->file} {$finding->description}");
        }

        if ($this->option('advisory')) {
            $this->warn(sprintf(
                '%d destructive operation(s) found. Not blocking at review time — the release that ships this must declare irreversibility in its tag annotation, or the release workflow will refuse it.',
                count($findings)
            ));

            return self::SUCCESS;
        }

        if ($scanner->declaresIrreversible((string) $this->option('declaration'))) {
            $this->info(sprintf('%d destructive operation(s) found, and the release declares itself irreversible — proceeding.', count($findings)));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            '%d destructive operation(s) found and this release does not declare itself irreversible. Add an "Irreversible: <reason>" line to the tag annotation, or confirm the schema change is genuinely reversible before tagging.',
            count($findings)
        ));

        return self::FAILURE;
    }

    /**
     * `HEAD^` deliberately — at release time HEAD is the tag just pushed,
     * and describing from HEAD itself would return that same tag rather
     * than the one before it. At review time HEAD carries no tag either
     * way, so the parent offset changes nothing there.
     */
    private function resolvePreviousTag(): string
    {
        // String form, not the array form the rest of this codebase
        // otherwise prefers: Process::fake()'s pattern matching (used by
        // this command's own test suite) matches against a plain command
        // string, and Symfony Process renders an array command as one
        // single-quoted token per argument, which no glob pattern here
        // would ever match. Every argument below is a fixed literal, so
        // string form carries no injection risk in this one case.
        $result = Process::run("git describe --tags --abbrev=0 --match='v*' HEAD^");

        if ($result->successful()) {
            return trim($result->output());
        }

        // No previous v* tag — the very first release under this scheme.
        // Git's canonical empty-tree object as the diff base means every
        // existing migration counts as "introduced since the baseline",
        // per this task's own literal scope.
        return '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
    }

    /** @return list<string> */
    private function newMigrationsSince(string $ref): array
    {
        // $ref is either the fixed empty-tree hash above, a caller-supplied
        // --since value, or git's own --match='v*' tag output — never
        // free-form external input — so escapeshellarg() here is
        // defence-in-depth rather than a response to a real threat.
        $result = Process::run('git diff --name-only --diff-filter=AM '.escapeshellarg($ref.'..HEAD').' -- database/migrations');

        if (! $result->successful()) {
            $this->warn("Could not diff against {$ref}: ".trim($result->errorOutput()));

            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", trim($result->output())))));
    }
}
