<?php

declare(strict_types=1);

use App\Models\ErrorPage;
use App\Services\Seo\ErrorPageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Error Page Administration Override
|--------------------------------------------------------------------------
|
| An SEO specialist's own copy for the 404 page ([TZ] §126) takes priority
| over the portal's static fallback the moment it exists, and the resolver's
| cache is dropped correctly when an administrator edits or removes it —
| the same ladder-plus-cache mechanism the SEO metadata templates use.
|
*/

function errorPageLanguage(): void
{
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('renders the administrator override instead of the static fallback once one exists', function (): void {
    errorPageLanguage();

    $errorPage = ErrorPage::query()->create(['status_code' => 404]);
    $errorPage->translations()->create([
        'locale' => 'en',
        'title' => 'We moved that page',
        'body' => 'Try the search instead — the old address no longer exists.',
    ]);

    $response = $this->get('/en/this-route-does-not-exist-anywhere');

    $response->assertNotFound()
        ->assertSee('We moved that page')
        ->assertSee('Try the search instead')
        ->assertDontSee(__('public.legal.not_found.title'));
});

it('falls back to the static copy for a language the override does not cover', function (): void {
    errorPageLanguage();

    $errorPage = ErrorPage::query()->create(['status_code' => 404]);
    $errorPage->translations()->create([
        'locale' => 'en',
        'title' => 'We moved that page',
        'body' => 'Try the search instead — the old address no longer exists.',
    ]);

    expect(app(ErrorPageResolver::class)->resolve(404, 'ru'))->toBeNull();
});

it('drops the cached override so an edit takes effect on the next request', function (): void {
    errorPageLanguage();

    $errorPage = ErrorPage::query()->create(['status_code' => 404]);
    $errorPage->translations()->create(['locale' => 'en', 'title' => 'Original', 'body' => 'Original body.']);

    $resolver = app(ErrorPageResolver::class);
    expect($resolver->resolve(404, 'en'))->toBe(['title' => 'Original', 'body' => 'Original body.']);

    $errorPage->translations()->where('locale', 'en')->update(['title' => 'Updated']);
    $resolver->forgetCache(404, 'en');

    expect($resolver->resolve(404, 'en')['title'])->toBe('Updated');
});

it('caches the absence of an override so an uncustomized status code is not queried every request', function (): void {
    errorPageLanguage();

    expect(app(ErrorPageResolver::class)->resolve(404, 'en'))->toBeNull();

    ErrorPage::query()->create(['status_code' => 404])
        ->translations()->create(['locale' => 'en', 'title' => 'Now customized', 'body' => 'Body.']);

    // The miss is cached forever until explicitly forgotten — the same
    // shape the record's own create page invalidates through in the panel.
    expect(app(ErrorPageResolver::class)->resolve(404, 'en'))->toBeNull();
});
