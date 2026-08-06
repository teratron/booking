<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

/**
 * Dependencies the scanner cannot see a use for, each present on purpose.
 *
 * A filter here is a claim that has to stay true. Anything listed must have a
 * reason and a point at which the reason expires — an entry with neither is
 * how an unused-dependency check turns into decoration.
 */
return static function (Configuration $config): Configuration {
    return $config
        // Issues the API tokens for the outward-facing REST contract. The
        // contract is not written yet, but its `personal_access_tokens` table
        // ships with the schema and the package is named in the project's
        // required set. Remove this filter when the first token-guarded route
        // is added; the scanner will then see the usage itself.
        ->addNamedFilter(NamedFilter::fromString('laravel/sanctum'))

        // Supplies the TOTP facade and middleware for Laravel. The panel
        // toolkit ships its own multi-factor implementation over the same
        // underlying `pragmarx/google2fa` algorithm package, and that is what
        // the staff panel uses — so the Laravel wrapper is currently carried
        // rather than called. Flagged for removal review: nothing in the
        // requirements needs the facade if the toolkit's implementation holds.
        ->addNamedFilter(NamedFilter::fromString('pragmarx/google2fa-laravel'));
};
