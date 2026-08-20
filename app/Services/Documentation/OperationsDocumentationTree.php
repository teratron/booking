<?php

declare(strict_types=1);

namespace App\Services\Documentation;

/**
 * Reads the procedure-name inventory of the three operator-documentation
 * renderings — English, Russian, and the machine-addressed rendering — as
 * plain file-stem sets, with no Laravel dependency, so the same class works
 * from an Architecture test (which boots no framework) and from an Artisan
 * command alike.
 *
 * A file missing from any one rendering is not a smaller gap than a missing
 * step within a file: an operator or an agent reaching for the Russian or
 * machine-addressed version of a procedure that exists only in English
 * finds nothing, precisely when a live incident is the reason they reached
 * for it at all.
 */
final class OperationsDocumentationTree
{
    private const array TREES = [
        'en' => '.md',
        'ru' => '.md',
        'agent' => '.prompt.md',
    ];

    public function __construct(private readonly string $operationsPath) {}

    /**
     * @return array<string, list<string>> tree name (`en`, `ru`, `agent`) =>
     *                                     sorted list of procedure stems present in that tree
     */
    public function stemsByTree(): array
    {
        $result = [];

        foreach (self::TREES as $tree => $extension) {
            $result[$tree] = $this->stems("{$this->operationsPath}/{$tree}", $extension);
        }

        return $result;
    }

    /** @return list<string> */
    private function stems(string $directory, string $extension): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $stems = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if (! str_ends_with($entry, $extension)) {
                continue;
            }

            $stems[] = substr($entry, 0, -strlen($extension));
        }

        sort($stems);

        return $stems;
    }
}
