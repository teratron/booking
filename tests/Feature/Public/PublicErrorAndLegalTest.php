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
