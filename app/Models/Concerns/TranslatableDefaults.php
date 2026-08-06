<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * `astrotomic/laravel-translatable` reads `$this->translationForeignKey` and
 * `$this->localeKey` directly (`$this->x ?: default()`), relying on
 * Eloquent's lenient magic `__get()` returning null for an attribute that
 * was never set. `Model::shouldBeStrict()` — enabled outside production
 * project-wide — turns that same access into a thrown
 * `MissingAttributeException` instead, since neither is a real column,
 * accessor, or relation. Declaring them as real, unset PHP properties
 * resolves the read through ordinary property access before Eloquent's
 * attribute system ever sees it, restoring the package's own expected
 * "unset means use the default" behaviour without touching strict mode
 * itself.
 *
 * `$translationModel` is deliberately not declared here even though the
 * package reads it the same way: PHP raises a fatal composition error when
 * a trait and the class using it declare the same property with different
 * defaults, and one model (`Object_`) must override it. Declared
 * individually on every Translatable model instead.
 */
trait TranslatableDefaults
{
    public ?string $translationForeignKey = null;

    public ?string $localeKey = null;
}
