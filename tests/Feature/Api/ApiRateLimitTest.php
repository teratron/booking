<?php

declare(strict_types=1);

use App\Models\ApiClient;
use App\Models\Module;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Services\Modules\ModuleAdministrator;
use App\Support\Api\ApiResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public API — Rate Limiting, Consumption, and Generated Documentation
|--------------------------------------------------------------------------
|
| Per-token rate limiting applies before any catalog query runs — a limiter
| checked after the query would offer the database no protection at all —
| and the published documentation is read from the route table and each
| endpoint's own validation rules, never maintained by hand.
|
*/

function apiRateLimitEnableModule(): void
{
    $moduleId = DB::table('modules')->insertGetId([
        'key' => 'api', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $module = Module::query()->findOrFail($moduleId);
    $actor = User::factory()->create();

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);
}

function apiRateLimitToken(?int $rateLimitPerMinute): string
{
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Rate Limit Test Client', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'rate-limit-test', [ApiResource::Countries, ApiResource::Objects], [], [], $rateLimitPerMinute, null, $actor,
    );

    return $newAccessToken->plainTextToken;
}

it('serves requests up to the configured limit, then 429s with a retry hint', function (): void {
    apiRateLimitEnableModule();
    $token = apiRateLimitToken(rateLimitPerMinute: 2);

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/countries')->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/countries')->assertOk();

    $blocked = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/countries');

    $blocked->assertStatus(429);
    expect($blocked->headers->has('Retry-After'))->toBeTrue()
        ->and((int) $blocked->headers->get('Retry-After'))->toBeGreaterThan(0);
});

it('short-circuits the limiter before any catalog query runs', function (): void {
    apiRateLimitEnableModule();
    $token = apiRateLimitToken(rateLimitPerMinute: 1);

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/objects')->assertOk();

    DB::enableQueryLog();
    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/objects')->assertStatus(429);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $objectQueries = array_filter($queries, static fn (array $entry): bool => str_contains(mb_strtolower((string) $entry['query']), 'objects'));

    expect($objectQueries)->toBe([]);
});

it('falls back to the portal-wide default limit when a token has no override', function (): void {
    apiRateLimitEnableModule();
    DB::table('settings')->insert([
        'key' => 'api.default_rate_limit_per_minute', 'value' => json_encode(1),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $token = apiRateLimitToken(rateLimitPerMinute: null);

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/countries')->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/countries')->assertStatus(429);
});

it('publishes a generated document listing every registered API route', function (): void {
    apiRateLimitEnableModule();

    $registeredCount = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'api.v1.'))
        ->count();

    $response = $this->getJson('/api/v1/docs')->assertOk();
    $endpoints = $response->json('endpoints');

    expect($endpoints)->toHaveCount($registeredCount)
        ->and(collect($endpoints)->pluck('name')->all())->toContain('api.v1.objects.index', 'api.v1.countries.index', 'api.v1.status');

    $objectsEndpoint = collect($endpoints)->firstWhere('name', 'api.v1.objects.index');

    expect($objectsEndpoint['ability'])->toBe('objects')
        ->and($objectsEndpoint['requires_token'])->toBeTrue()
        ->and($objectsEndpoint['parameters'])->toHaveKey('territory')
        ->and($objectsEndpoint['parameters'])->toHaveKey('per_page');
});
