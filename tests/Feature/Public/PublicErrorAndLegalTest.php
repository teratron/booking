<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Error Page & Static Legal Pages
|--------------------------------------------------------------------------
|
| A 404 renders the shell's own error page — never a framework default —
| and is itself never indexable. Privacy policy and terms of use render,
| are reachable without authentication, and are linked persistently from
| the shell footer.
|
*/

function publicErrorAndLegalRegistry(): void
{
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function publicErrorAndLegalRegistryBothLocales(): void
{
    DB::table('languages')->insert([
        ['code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);
}

it('renders the shell 404 page — not a framework default — for an unresolved route, carrying a noindex directive across all four responsive treatments', function (): void {
    publicErrorAndLegalRegistry();

    $response = $this->get('/en/this-route-does-not-exist-anywhere');

    $response->assertNotFound();
    $response->assertSee(__('public.legal.not_found.title'));
    $response->assertSee('<meta name="robots" content="noindex">', false);
    // Proves it is the real shell (not a bare error page): the same
    // responsive markers T-5A01's own shell test asserts on.
    $response->assertSee('mobileOpen = !mobileOpen', false);
    $response->assertSee('sm:flex', false);
    $response->assertSee('hidden lg:block', false);
});

it('404s the same way for an unresolved route outside any lang segment', function (): void {
    publicErrorAndLegalRegistry();

    $this->get('/this-route-does-not-exist-anywhere')
        ->assertNotFound()
        ->assertSee(__('public.legal.not_found.title'));
});

it('renders the privacy policy page, reachable without authentication', function (): void {
    publicErrorAndLegalRegistry();

    $response = $this->get('/en/privacy-policy');

    $response->assertOk()
        ->assertSee(__('public.legal.privacy.title'))
        ->assertSee(__('public.legal.privacy.body')[0]);
});

it('renders the terms of use page, reachable without authentication', function (): void {
    publicErrorAndLegalRegistry();

    $response = $this->get('/en/terms');

    $response->assertOk()
        ->assertSee(__('public.legal.terms.title'))
        ->assertSee(__('public.legal.terms.body')[0]);
});

it('links both legal pages persistently from the shell footer', function (): void {
    publicErrorAndLegalRegistry();

    $response = $this->get('/en/privacy-policy');

    $response->assertSee('href="'.route('public.legal.privacy', ['lang' => 'en']).'"', false)
        ->assertSee('href="'.route('public.legal.terms', ['lang' => 'en']).'"', false);
});

it('renders the About page in both locales, reachable without authentication', function (): void {
    publicErrorAndLegalRegistryBothLocales();

    $this->get('/en/about')
        ->assertOk()
        ->assertSee(__('public.static.about.title'))
        ->assertSee(__('public.static.about.body')[0]);

    $this->get('/ru/about')
        ->assertOk()
        ->assertSee(trans('public.static.about.title', [], 'ru'))
        ->assertSee(trans('public.static.about.body', [], 'ru')[0]);
});

it('renders the Contacts page in both locales with the portal contact details, reachable without authentication', function (): void {
    publicErrorAndLegalRegistryBothLocales();
    DB::table('settings')->insert([
        ['key' => 'portal.contact_phone', 'value' => json_encode('+373 22 000000'), 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'portal.contact_email', 'value' => json_encode('hello@example.test'), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->get('/en/contacts')
        ->assertOk()
        ->assertSee(__('public.static.contacts.title'))
        ->assertSee('+373 22 000000')
        ->assertSee('hello@example.test');

    $this->get('/ru/contacts')
        ->assertOk()
        ->assertSee(trans('public.static.contacts.title', [], 'ru'))
        ->assertSee('+373 22 000000')
        ->assertSee('hello@example.test');
});

it('links both About and Contacts persistently from the shell footer, no longer degrading to inert text', function (): void {
    publicErrorAndLegalRegistry();

    $this->get('/en/about')
        ->assertSee('href="'.route('public.about', ['lang' => 'en']).'"', false)
        ->assertSee('href="'.route('public.contacts', ['lang' => 'en']).'"', false);
});
