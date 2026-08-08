<?php

declare(strict_types=1);

namespace App\Services\Localization;

use Astrotomic\Translatable\Contracts\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Discovers every translatable model by the contract it implements, not by
 * a maintained list — a model added in a later phase is picked up the
 * moment it implements `Translatable`, with no change here.
 */
final class TranslatableEntityRegistry
{
    /** @var ?list<TranslatableEntity> */
    private ?array $entries = null;

    /** @return list<TranslatableEntity> */
    public function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $entries = [];

        foreach ($this->discoverModelClasses() as $class) {
            if (! is_a($class, Translatable::class, true)) {
                continue;
            }

            /** @var Model&Translatable $model */
            $model = new $class;
            $relation = $model->translations();

            /** @phpstan-ignore-next-line method.notFound (provided by astrotomic/laravel-translatable) */
            $foreignKey = $model->getTranslationRelationKey();
            /** @var list<string> $translatedAttributes @phpstan-ignore-next-line property.notFound (provided by astrotomic/laravel-translatable) */
            $translatedAttributes = $model->translatedAttributes;

            $entries[] = new TranslatableEntity(
                modelClass: $class,
                table: $model->getTable(),
                translationTable: $relation->getRelated()->getTable(),
                foreignKey: $foreignKey,
                translatedAttributes: $translatedAttributes,
                labelColumn: $translatedAttributes[0],
            );
        }

        return $this->entries = $entries;
    }

    public function find(string $modelClass): ?TranslatableEntity
    {
        foreach ($this->entries() as $entry) {
            if ($entry->modelClass === $modelClass) {
                return $entry;
            }
        }

        return null;
    }

    /** @return list<class-string> */
    private function discoverModelClasses(): array
    {
        $classes = [];

        foreach (File::allFiles(app_path('Models')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = Str::of($file->getRelativePathname())
                ->beforeLast('.php')
                ->replace('/', '\\')
                ->replace('\\\\', '\\');

            $class = 'App\\Models\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
