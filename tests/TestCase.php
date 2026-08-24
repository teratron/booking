<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Registries such as active languages, countries, and object types
     * (see App\Services\Shell\PublicShellDataProvider) are cached with no
     * expiry short enough to matter for a single test run, invalidated
     * only through Eloquent write events. Fixtures across this suite seed
     * those same tables via raw DB::table() inserts for speed, which never
     * fires those events — so a cache entry warmed by one test would
     * otherwise survive that test's RefreshDatabase rollback (the cache
     * store isn't part of the transaction) and leak into the next one.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
