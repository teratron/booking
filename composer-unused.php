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
return static fn (Configuration $config): Configuration => $config
    // Its own PHP `use` statement lives in bootstrap/app.php
    // (Sentry\Laravel\Integration::handles(...), wiring queue and scheduler
    // exception capture) — outside app/, the only tree this scanner walks
    // for usage. Expires if the scanner ever gains bootstrap/ in its scan
    // path, or if that wiring moves into app/.
    ->addNamedFilter(NamedFilter::fromString('sentry/sentry-laravel'))
    // Every real `Laravel\Pulse\*` class reference lives in config/pulse.php
    // (the recorders array, the Authorize middleware) — outside app/, the
    // only tree this scanner walks. app/'s own Pulse wiring
    // (AppServiceProvider::registerPulseAuthorization()) defines the
    // "viewPulse" ability by string name, which resolves at runtime through
    // Pulse's own Authorize middleware rather than a class the scanner could
    // see. Expires on the same condition as the filter above.
    ->addNamedFilter(NamedFilter::fromString('laravel/pulse'));
