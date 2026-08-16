<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;

/**
 * Dependencies the scanner cannot see a use for, each present on purpose.
 *
 * A filter here is a claim that has to stay true. Anything listed must have a
 * reason and a point at which the reason expires — an entry with neither is
 * how an unused-dependency check turns into decoration.
 */
return static fn (Configuration $config): Configuration => $config;
