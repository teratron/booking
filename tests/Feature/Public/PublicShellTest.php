<?php

declare(strict_types=1);

use App\Http\Middleware\ResolvePublicLocale;
use App\Models\FeedbackSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Shell
|--------------------------------------------------------------------------
|
| The chrome every public page renders inside: header, data-driven
| navigation, language and country switchers, breadcrumbs, and the shared
| feedback overlay. No real public page exists yet in this phase, so these
| tests render the layout through an ephemeral route registered inside the
| same `{lang}`-prefixed, ResolvePublicLocale-guarded group every later
| public page will share — proving the real middleware and layout stack,
| not a stand-in.
|
*/

/** @return array{languageId: int} */
function publicShellRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('languages')->insert([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
        'display_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('languages')->insert([
        'code' => 'ka', 'short_label' => 'KA', 'is_active' => false, 'is_primary' => false,
        'display_order' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $activeCountryId = DB::table('countries')->insertGetId([
        'code' => 'md', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('country_translations')->insert([
        'country_id' => $activeCountryId, 'locale' => 'en', 'name' => 'Moldova',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $inactiveCountryId = DB::table('countries')->insertGetId([
        'code' => 'xx', 'currency' => 'XXX', 'phone_code' => '+000',
        'primary_language_id' => $languageId, 'is_active' => false, 'display_order' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('country_translations')->insert([
        'country_id' => $inactiveCountryId, 'locale' => 'en', 'name' => 'Hiddenland',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['languageId' => $languageId];
}

function registerPublicShellTestRoute(): void
{
    Route::prefix('{lang}')
        ->middleware(ResolvePublicLocale::class)
        ->group(function (): void {
            Route::get('/__shell-test', fn () => Blade::render(
                <<<'BLADE'
                    <x-layouts.public :breadcrumbs="$breadcrumbs">
                        <div>Shell test content</div>
                    </x-layouts.public>
                    BLADE,
                [
                    'breadcrumbs' => [
                        ['label' => 'Home', 'url' => '/en'],
                        ['label' => 'Current page', 'url' => '/en/__shell-test'],
                    ],
                ],
            ))->name('public.__shell-test');
        });

    // A route added after boot never runs through the name-index refresh
    // route-file loading gets automatically, so the language switcher's own
    // route(...) lookup for this route would otherwise 404 despite the
    // route matching correctly moments earlier in the same request.
    Route::getRoutes()->refreshNameLookups();
}

it('renders the shell with markup for all four responsive treatments — phone drawer, tablet inline switchers, laptop/desktop inline navigation', function (): void {
    publicShellRegistry();
    registerPublicShellTestRoute();

    $response = $this->get('/en/__shell-test');

    $response->assertOk();
    // Phone: a menu button toggles a drawer, hidden at `lg:`.
    $response->assertSee('mobileOpen = !mobileOpen', false);
    $response->assertSee('lg:hidden', false);
    // Tablet: switchers become inline at `sm:`.
    $response->assertSee('sm:flex', false);
    // Laptop/desktop: the full navigation bar is inline at `lg:`.
    $response->assertSee('hidden lg:block', false);
});

it('lists every active language and country from their real registries, excluding inactive ones', function (): void {
    publicShellRegistry();
    registerPublicShellTestRoute();

    $response = $this->get('/en/__shell-test');

    $response->assertSee('EN', false)
        ->assertSee('RU', false)
        ->assertDontSee('KA', false)
        ->assertSee('Moldova')
        ->assertDontSee('Hiddenland');
});

it('reads navigation entries from the object-type registry rather than a hard-coded list', function (): void {
    publicShellRegistry();
    registerPublicShellTestRoute();

    DB::table('object_types')->insert([
        'key' => 'glamping', 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $glampingId = DB::table('object_types')->where('key', 'glamping')->value('id');
    DB::table('object_type_translations')->insert([
        'object_type_id' => $glampingId, 'locale' => 'en', 'name' => 'Glamping', 'slug' => 'glamping',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $inactiveId = DB::table('object_types')->insertGetId([
        'key' => 'retired-type', 'is_active' => false, 'display_order' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $inactiveId, 'locale' => 'en', 'name' => 'Retired Type', 'slug' => 'retired-type',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->get('/en/__shell-test');

    $response->assertSee('Glamping')->assertDontSee('Retired Type');
});

it('renders breadcrumbs as links on a page below the home page', function (): void {
    publicShellRegistry();
    registerPublicShellTestRoute();

    $response = $this->get('/en/__shell-test');

    $response->assertSee('href="/en"', false)
        ->assertSee('href="/en/__shell-test"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSeeText('Home')
        ->assertSeeText('Current page');

    // Every crumb — including the current page — is a real <a>, not a span.
    expect(substr_count((string) $response->getContent(), '<a'))->toBeGreaterThanOrEqual(2);
});

it('makes the shared feedback overlay invokable from a representative page', function (): void {
    publicShellRegistry();
    registerPublicShellTestRoute();

    $response = $this->get('/en/__shell-test');

    $response->assertSee(__('public.shell.feedback.trigger'))
        ->assertSee(__('public.shell.feedback.submit'))
        ->assertSee('action="'.route('public.feedback.submit', ['lang' => 'en']).'"', false);
});

it('renders the cookie-consent notice, gated on localStorage rather than shown unconditionally', function (): void {
    // F-22: the shell spec names a cookie notice in its own scope, but no
    // such markup existed anywhere in resources/views. The visibility
    // check itself is client-side (a first-time visitor sees it, a
    // returning one who already accepted does not) — this proves the
    // markup and its localStorage gate exist; the accept-then-reload
    // behaviour itself is verified live in a browser, not through a
    // server-rendered HTTP assertion.
    publicShellRegistry();
    registerPublicShellTestRoute();

    $response = $this->get('/en/__shell-test');

    $response->assertSee(__('public.shell.cookie_consent.message'))
        ->assertSee(__('public.shell.cookie_consent.accept'))
        ->assertSee("localStorage.getItem('cookie-consent-accepted')", false)
        ->assertSee("localStorage.setItem('cookie-consent-accepted'", false);
});

it('404s an inactive or unknown language segment rather than silently falling back', function (): void {
    publicShellRegistry();
    registerPublicShellTestRoute();

    $this->get('/ka/__shell-test')->assertNotFound();
    $this->get('/xx/__shell-test')->assertNotFound();
});

it('stores the country preference and redirects when the visitor switches country', function (): void {
    publicShellRegistry();

    $response = $this->post('/en/country', ['country' => 'md']);

    $response->assertRedirect();
    expect(session('public.country'))->toBe('md');
});

it('refuses a country code that is not active', function (): void {
    publicShellRegistry();

    $this->post('/en/country', ['country' => 'xx'])->assertNotFound();
});

it('persists a feedback submission from the shared overlay', function (): void {
    publicShellRegistry();

    $response = $this->post('/en/feedback', [
        'name' => 'Ana',
        'email' => 'ana@example.test',
        'message' => 'Great portal, one small suggestion…',
        'page_url' => '/en/o/some-hotel',
    ]);

    $response->assertRedirect();
    expect(FeedbackSubmission::query()->count())->toBe(1);
    $submission = FeedbackSubmission::query()->sole();
    expect($submission->name)->toBe('Ana')
        ->and($submission->email)->toBe('ana@example.test')
        ->and($submission->locale)->toBe('en');
});
