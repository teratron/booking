<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * One declared portal setting: its key, the shape of its value, what it is
 * before an administrator ever touches it, and whether changing it is
 * restricted to the chief administrator.
 *
 * The declaration is what makes the flat `key`/`jsonb` table safe to use. Read
 * raw, that table scatters string keys through the codebase, returns `null`
 * for anything unset, and loses every type — three failures that only show up
 * at the call site, one call site at a time.
 */
final class SettingDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly string $type,
        public readonly mixed $default,
        public readonly bool $isCritical = false,
    ) {}

    /**
     * Coerces a stored or submitted value to the declared type.
     *
     * A setting written through an import, a console command, or a hand-edited
     * row can arrive as a string where an integer is declared. Coercing on
     * read means one wrong write does not become a type error in whichever
     * request happens to read it next.
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }

        return match ($this->type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            'array' => is_array($value) ? $value : [$value],
            default => (string) $value,
        };
    }
}
