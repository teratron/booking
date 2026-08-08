<?php

declare(strict_types=1);

namespace App\Services\Localization;

/**
 * One discovered translatable model's shape, resolved from the model
 * itself rather than declared by hand — see `TranslatableEntityRegistry`.
 */
final readonly class TranslatableEntity
{
    /**
     * @param  class-string  $modelClass
     * @param  list<string>  $translatedAttributes
     */
    public function __construct(
        public string $modelClass,
        public string $table,
        public string $translationTable,
        public string $foreignKey,
        public array $translatedAttributes,
        public string $labelColumn,
    ) {}
}
