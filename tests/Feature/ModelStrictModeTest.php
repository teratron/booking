<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Eloquent strict mode is active outside production', function (): void {
    User::factory()->create(['name' => 'Strict Mode Probe']);

    // A partial select is the real-world shape this guards: a query that
    // deliberately narrows its columns, followed by code that assumes a
    // column outside that set is present. Model::make() never triggers this
    // path — only a genuinely partial hydration from the database does.
    $partiallyHydrated = User::query()->select('id')->firstOrFail();

    expect(fn () => $partiallyHydrated->getAttribute('name'))
        ->toThrow(MissingAttributeException::class);
});
