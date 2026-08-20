<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Review-time half of documentation parity (the other half is the
 * Architecture-suite test asserting the three trees hold the same
 * procedure set at rest). This command instead looks at one pull request's
 * own diff: editing `docs/operations/en/deploy.md` without touching its
 * `ru/` and `agent/` counterparts in the same change is exactly how a
 * translation goes stale — discovered later by whoever most needs it
 * correct, during an incident. Failing the pull request that caused the
 * gap is cheaper than discovering the gap that way.
 */
final class CheckOperationsDocumentationParity extends Command
{
    protected $signature = 'docs:check-operations-parity
        {--base= : Git ref to diff this change against (default: origin/GITHUB_BASE_REF in a pull request, else HEAD^)}';

    protected $description = 'Fail a change that edits one operator-documentation rendering without touching its counterparts';

    private const array TREES = [
        'en' => ['dir' => 'docs/operations/en', 'extension' => '.md'],
        'ru' => ['dir' => 'docs/operations/ru', 'extension' => '.md'],
        'agent' => ['dir' => 'docs/operations/agent', 'extension' => '.prompt.md'],
    ];

    public function handle(): int
    {
        $base = $this->option('base') ?: $this->resolveDefaultBase();
        $changedFiles = $this->changedFiles($base);

        if ($changedFiles === []) {
            $this->info("No files changed since {$base}.");

            return self::SUCCESS;
        }

        $touchedStemsByTree = $this->groupByTreeAndStem($changedFiles);
        $everyTouchedStem = array_unique(array_merge(...array_values($touchedStemsByTree)));

        if ($everyTouchedStem === []) {
            $this->info('No operator-documentation files changed — nothing to check.');

            return self::SUCCESS;
        }

        $incomplete = [];

        foreach ($everyTouchedStem as $stem) {
            $missingTrees = array_filter(
                array_keys(self::TREES),
                fn (string $tree): bool => ! in_array($stem, $touchedStemsByTree[$tree], true)
            );

            if ($missingTrees !== []) {
                $incomplete[$stem] = array_values($missingTrees);
            }
        }

        if ($incomplete === []) {
            $this->info(sprintf('%d procedure(s) changed, all three renderings touched together — parity holds.', count($everyTouchedStem)));

            return self::SUCCESS;
        }

        foreach ($incomplete as $stem => $missingTrees) {
            $this->error(sprintf(
                '::error::"%s" was edited but its %s rendering(s) were not touched in this change.',
                $stem,
                implode(' and ', $missingTrees)
            ));
        }

        $this->error('Touch every rendering of a changed procedure in the same pull request, or revert the ones left behind.');

        return self::FAILURE;
    }

    private function resolveDefaultBase(): string
    {
        $githubBaseRef = getenv('GITHUB_BASE_REF');

        if (is_string($githubBaseRef) && $githubBaseRef !== '') {
            return 'origin/'.$githubBaseRef;
        }

        return 'HEAD^';
    }

    /** @return list<string> */
    private function changedFiles(string $base): array
    {
        $result = Process::run('git diff --name-only --diff-filter=ACMR '.escapeshellarg($base.'..HEAD').' -- docs/operations');

        if (! $result->successful()) {
            $this->warn("Could not diff against {$base}: ".trim($result->errorOutput()));

            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", trim($result->output())))));
    }

    /**
     * @param  list<string>  $changedFiles
     * @return array<string, list<string>> tree name => stems touched in that tree by this diff
     */
    private function groupByTreeAndStem(array $changedFiles): array
    {
        $touched = array_fill_keys(array_keys(self::TREES), []);

        foreach ($changedFiles as $file) {
            foreach (self::TREES as $tree => $shape) {
                $prefix = $shape['dir'].'/';
                $extension = $shape['extension'];

                if (str_starts_with($file, $prefix) && str_ends_with($file, $extension)) {
                    $touched[$tree][] = substr($file, strlen($prefix), -strlen($extension));
                }
            }
        }

        return $touched;
    }
}
