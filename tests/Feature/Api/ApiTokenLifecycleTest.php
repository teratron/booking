<?php

declare(strict_types=1);

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\Module;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Services\Modules\ModuleAdministrator;
use App\Support\Api\ApiResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public API — Token Lifecycle
|--------------------------------------------------------------------------
|
| Issuance, revocation, and scope change on the token model Sanctum's own
| tables carry, journalled the same way every other privileged event in
| this project is.
|
*/

function apiTokenModuleFixture(): int
{
    return DB::table('modules')->insertGetId([
        'key' => 'api', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function enableApiModule(User $actor): void
{
    $moduleId = apiTokenModuleFixture();
    $module = Module::query()->findOrFail($moduleId);

    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $actor);
}

it('stores only the token hash, never the clear value', function (): void {
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Acme Travel', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'primary', [ApiResource::Countries], [], [], null, null, $actor,
    );

    [$id, $secret] = explode('|', $newAccessToken->plainTextToken, 2);

    $stored = DB::table('personal_access_tokens')->find($id);

    expect($stored->token)->toBe(hash('sha256', $secret))
        ->and($stored->token)->not->toBe($newAccessToken->plainTextToken)
        ->and($stored->token)->not->toContain($secret);
});

it('rejects a revoked token on its very next request', function (): void {
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Acme Travel', 'is_active' => true, 'created_by' => $actor->id]);
    enableApiModule($actor);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'primary', [ApiResource::Countries], [], [], null, null, $actor,
    );
    $plainTextToken = $newAccessToken->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/v1/token')
        ->assertOk()
        ->assertJson(['client' => 'Acme Travel', 'resources' => ['countries']]);

    app(ApiTokenService::class)->revoke($newAccessToken->accessToken, $actor);

    // A real client's second call is a genuinely new HTTP request with no
    // guard state to carry over; forgetGuards() reproduces that here since
    // this test's two calls otherwise share one container's cached guard.
    auth()->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/v1/token')
        ->assertUnauthorized();
});

it('journals issuance, revocation, and every scope change', function (): void {
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Acme Travel', 'is_active' => true, 'created_by' => $actor->id]);

    $newAccessToken = app(ApiTokenService::class)->issue(
        $client, 'primary', [ApiResource::Countries], [1], [], null, null, $actor,
    );
    /** @var ApiToken $token */
    $token = $newAccessToken->accessToken;

    app(ApiTokenService::class)->changeScope(
        $token, [ApiResource::Countries, ApiResource::Objects], [1, 2], [3], $actor,
    );

    app(ApiTokenService::class)->revoke($token, $actor);

    $events = Audit::query()->where('auditable_type', ApiClient::class)
        ->where('auditable_id', $client->id)
        ->pluck('event');

    expect($events)->toContain('api_token_issued')
        ->toContain('api_token_scope_changed')
        ->toContain('api_token_revoked');
});

it('resolves country and category scope rows the same way an administrator grant does', function (): void {
    $actor = User::factory()->create();
    $client = ApiClient::create(['name' => 'Acme Travel', 'is_active' => true, 'created_by' => $actor->id]);

    $unrestricted = app(ApiTokenService::class)->issue(
        $client, 'unrestricted', [ApiResource::Countries], [], [], null, null, $actor,
    );
    $constraint = app(ApiTokenService::class)->scopeConstraint($unrestricted->accessToken);
    expect($constraint->isUnrestricted)->toBeTrue();

    $scoped = app(ApiTokenService::class)->issue(
        $client, 'scoped', [ApiResource::Objects], [5, 6], [7], null, null, $actor,
    );
    $constraint = app(ApiTokenService::class)->scopeConstraint($scoped->accessToken);

    expect($constraint->isUnrestricted)->toBeFalse()
        ->and($constraint->countryIds)->toEqualCanonicalizing([5, 6])
        ->and($constraint->categoryIds)->toEqual([7]);
});
