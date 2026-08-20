<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| App Service Provider Boot Without a Reachable Database
|--------------------------------------------------------------------------
|
| `composer install`'s own `post-autoload-dump` hook runs `artisan
| package:discover`, which boots this provider before `.env` exists
| anywhere the framework has ever run from a fresh checkout — a fresh
| production image build and CI's own "Install dependencies" step both
| included. `Schema::hasTable()` throws rather than returning false when
| no database connection can be opened at all, which crashed boot()
| outright the first time a production image was built from this
| repository (confirmed live: the exact same failure independently broke
| CI's existing "Install dependencies" step, unrelated to that build).
|
*/

test('AppServiceProvider::boot() tolerates a database that cannot be reached at all', function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => '/nonexistent/path/database.sqlite',
    ]);

    // Forces a fresh PDO connection attempt against the broken path above —
    // the test's own default connection (booking_testing) is otherwise
    // already resolved and cached from an earlier point in the boot cycle.
    DB::purge('sqlite');

    // Sanity check that the failure mode this test guards against still
    // exists: a call would throw here if this stopped being true, silently
    // making the assertion below meaningless. `Exception::class`, not
    // `Throwable::class` — Pest's `toThrow()` only recognises a concrete
    // class as a type check; an interface silently falls back to a
    // message-substring match instead, which every real message here fails
    // trivially and would make this assertion pass for the wrong reason.
    expect(fn () => DB::connection('sqlite')->getSchemaBuilder()->hasTable('anything'))
        ->toThrow(Exception::class);

    // The real assertion: boot() itself — which calls both
    // overlayInterfaceCatalogFromDatabase() and syncTranslatableLocales(),
    // each gated on the same unreachable connection — must not propagate
    // that exception.
    expect(fn () => (new AppServiceProvider(app()))->boot())->not->toThrow(Exception::class);
});
