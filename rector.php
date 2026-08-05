<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// Scope is deliberately narrow: dead-code removal and framework-major upgrade
// automation, not stylistic rewrites — Pint already owns code style, and a
// second tool rewriting the same lines would fight it on every run.
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/database',
        __DIR__.'/routes',
    ])
    ->withSkip([
        __DIR__.'/bootstrap/cache',
    ])
    // No argument resolves the target PHP version from this project's own
    // composer.json `require.php` constraint, so the target stays correct
    // across every future PHP bump without a config edit here.
    ->withPhpSets()
    // Version-based set provider: resolves the applicable Laravel rule set
    // from the installed illuminate/framework version at run time, so a
    // future Laravel major upgrade needs no change to this file either.
    ->withComposerBased(laravel: true)
    ->withPreparedSets(deadCode: true)
    ->withImportNames(removeUnusedImports: true);
