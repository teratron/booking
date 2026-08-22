<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Root Entry
|--------------------------------------------------------------------------
|
| Every public page lives under a `{lang}` segment, which leaves the bare
| root as the one address carrying no language to read. It negotiates one
| against the active language registry and forwards into the localized
| home page, so the portal's front door reaches the portal.
|
| The registry drives the whole decision: which languages are candidates,
| and which one is the fallback. Activating or retiring a language is a
| data operation, and these tests assert that by changing only rows.
|
*/

function rootEntryLanguages(): void
{
    DB::table('languages')->insert([
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
}

it('forwards the bare root to the primary language home page', function (): void {
    rootEntryLanguages();

    $this->get('/')->assertRedirect(route('public.home', ['lang' => 'en']));
});

it('honours an Accept-Language naming an active language', function (): void {
    rootEntryLanguages();

    $this->get('/', ['Accept-Language' => 'ru'])
        ->assertRedirect(route('public.home', ['lang' => 'ru']));
});

it('falls back to the primary language when Accept-Language names an inactive one', function (): void {
    rootEntryLanguages();

    // `ka` exists in the registry but is retired — a retired language is
    // not a reachable address, so it must never be negotiated into.
    $this->get('/', ['Accept-Language' => 'ka'])
        ->assertRedirect(route('public.home', ['lang' => 'en']));
});

it('falls back to the primary language when Accept-Language names an unknown one', function (): void {
    rootEntryLanguages();

    $this->get('/', ['Accept-Language' => 'de-DE,de;q=0.9'])
        ->assertRedirect(route('public.home', ['lang' => 'en']));
});

it('respects the weighting in a multi-language Accept-Language header', function (): void {
    rootEntryLanguages();

    $this->get('/', ['Accept-Language' => 'de;q=0.9, ru;q=0.8'])
        ->assertRedirect(route('public.home', ['lang' => 'ru']));
});

it('redirects temporarily, never permanently', function (): void {
    rootEntryLanguages();

    // A 301 would be cached by browsers and intermediaries and pin every
    // later visitor to whichever language was negotiated first.
    $this->get('/')->assertStatus(302);
});

it('follows the registry when a different language is made primary', function (): void {
    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => false,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('languages')->insert([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => true,
        'display_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get('/', ['Accept-Language' => 'de-DE,de;q=0.9'])
        ->assertRedirect(route('public.home', ['lang' => 'ru']));
});
