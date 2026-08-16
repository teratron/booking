<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Registry Seeders
|--------------------------------------------------------------------------
|
| The three assertions this task's Verify line names directly: the phased
| language activation set, every launch country resolving its own
| (inactive) primary language without a validation error, and the module
| registry's dependency chain — each checked against a real `--seed` run,
| not against the seeder classes' source.
|
*/

beforeEach(function (): void {
    Artisan::call('db:seed');
});

test('exactly two languages are active and three ship present but inactive', function (): void {
    $active = DB::table('languages')->where('is_active', true)->pluck('code')->sort()->values();
    $inactive = DB::table('languages')->where('is_active', false)->pluck('code')->sort()->values();

    expect($active->all())->toBe(['en', 'ru']);
    expect($inactive->all())->toBe(['ka', 'ro', 'uk']);
});

test('every launch country resolves its own inactive primary language without error', function (): void {
    $countries = DB::table('countries')
        ->join('languages', 'countries.primary_language_id', '=', 'languages.id')
        ->select('countries.code as country', 'languages.code as language', 'languages.is_active')
        ->get()
        ->keyBy('country');

    expect($countries)->toHaveCount(3);
    expect($countries['MD']->language)->toBe('ro');
    expect($countries['UA']->language)->toBe('uk');
    expect($countries['GE']->language)->toBe('ka');

    // The point the Verify line exists to prove: none of these are active,
    // and the foreign key still resolved cleanly.
    foreach ($countries as $country) {
        expect((bool) $country->is_active)->toBeFalse();
    }
});

test('the module registry matches the launch matrix with its dependency chain', function (): void {
    $modules = DB::table('modules')->pluck('default_state', 'key');

    expect($modules['reviews'])->toBe('enabled');
    expect($modules['guest_accounts'])->toBe('disabled');
    expect($modules['booking'])->toBe('disabled');
    expect($modules['payment'])->toBe('disabled');
    expect($modules['api'])->toBe('disabled');

    $moduleIdByKey = DB::table('modules')->pluck('id', 'key');

    $bookingDependsOnGuestAccounts = DB::table('module_dependencies')
        ->where('module_id', $moduleIdByKey['booking'])
        ->where('depends_on_module_id', $moduleIdByKey['guest_accounts'])
        ->exists();

    $paymentDependsOnBooking = DB::table('module_dependencies')
        ->where('module_id', $moduleIdByKey['payment'])
        ->where('depends_on_module_id', $moduleIdByKey['booking'])
        ->exists();

    expect($bookingDependsOnGuestAccounts)->toBeTrue();
    expect($paymentDependsOnBooking)->toBeTrue();
});

test('migrate:fresh --seed applies cleanly and every registry ships non-empty', function (): void {
    Artisan::call('migrate:fresh', ['--seed' => true]);

    expect(DB::table('languages')->count())->toBe(5);
    expect(DB::table('countries')->count())->toBe(3);
    expect(DB::table('territory_levels')->count())->toBe(12);
    expect(DB::table('object_types')->count())->toBeGreaterThan(0);
    expect(DB::table('amenity_groups')->count())->toBeGreaterThan(0);
    expect(DB::table('amenities')->count())->toBeGreaterThan(0);
    expect(DB::table('contact_channel_types')->count())->toBeGreaterThan(0);
    expect(DB::table('placement_tiers')->count())->toBe(4);
    expect(DB::table('placement_packages')->count())->toBe(4);
    expect(DB::table('modules')->count())->toBe(5);
    expect(DB::table('notification_channels')->count())->toBeGreaterThan(0);
    expect(DB::table('notification_types')->count())->toBe(10);
});
