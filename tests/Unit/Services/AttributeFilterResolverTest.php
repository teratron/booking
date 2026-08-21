<?php

declare(strict_types=1);

use App\Models\ObjectType;
use App\Services\Catalog\AttributeFilterResolver;

/*
|--------------------------------------------------------------------------
| Attribute Filter Resolution
|--------------------------------------------------------------------------
|
| The catalog's `attrs` selection round-trips through the URL, so it arrives
| with entirely unconstrained keys and values. This resolver is what
| reconciles it against the object type's own declared schema before any of
| it becomes a query filter.
|
| No database: the resolver reads `attribute_schema` off the model and
| nothing else, so an unsaved instance carrying that array exercises every
| branch.
|
*/

/** @param  list<array{key: string, type: string}>  $schema */
function typeDeclaring(array $schema): ObjectType
{
    $type = new ObjectType;
    $type->attribute_schema = $schema;

    return $type;
}

function resolveAttributes(ObjectType $type, array $raw): array
{
    return (new AttributeFilterResolver)->resolve($type, $raw);
}

it('keeps a numeric range the type declares', function (): void {
    $type = typeDeclaring([['key' => 'distance_to_sea', 'type' => 'number']]);

    expect(resolveAttributes($type, ['distance_to_sea' => ['max' => '500']]))
        ->toBe(['distance_to_sea' => ['max' => 500.0]]);
});

it('drops a key the type does not declare', function (): void {
    $type = typeDeclaring([['key' => 'distance_to_sea', 'type' => 'number']]);

    expect(resolveAttributes($type, ['not_declared' => 'anything']))->toBe([]);
});

it('drops a non-numeric bound rather than letting it reach the numeric cast', function (): void {
    $type = typeDeclaring([['key' => 'distance_to_sea', 'type' => 'number']]);

    // `(attributes ->> ?)::numeric >= 'abc'` is a Postgres error, which the
    // catalog page would surface as a 500 for a hand-edited query string.
    expect(resolveAttributes($type, ['distance_to_sea' => ['min' => 'abc']]))->toBe([]);
});

it('drops a range applied to a text attribute', function (): void {
    $type = typeDeclaring([['key' => 'catering', 'type' => 'text']]);

    expect(resolveAttributes($type, ['catering' => ['min' => 1]]))->toBe([]);
});

it('compares a boolean attribute against the text json stores it as', function (): void {
    $type = typeDeclaring([['key' => 'has_pool', 'type' => 'boolean']]);

    // `attributes ->> 'has_pool'` reads a real JSON boolean back as the
    // text `true` — a raw `1` would compare as `'1' = 'true'` and match
    // nothing at all.
    expect(resolveAttributes($type, ['has_pool' => '1']))->toBe(['has_pool' => 'true'])
        ->and(resolveAttributes($type, ['has_pool' => '0']))->toBe(['has_pool' => 'false'])
        ->and(resolveAttributes($type, ['has_pool' => 'true']))->toBe(['has_pool' => 'true']);
});

it('drops an unrecognised boolean rather than defaulting it to false', function (): void {
    $type = typeDeclaring([['key' => 'has_pool', 'type' => 'boolean']]);

    expect(resolveAttributes($type, ['has_pool' => 'maybe']))->toBe([]);
});

it('drops empty selections', function (): void {
    $type = typeDeclaring([['key' => 'catering', 'type' => 'text']]);

    expect(resolveAttributes($type, ['catering' => '']))->toBe([])
        ->and(resolveAttributes($type, ['catering' => []]))->toBe([])
        ->and(resolveAttributes($type, ['catering' => null]))->toBe([]);
});

it('skips a malformed schema entry instead of assuming it is text', function (): void {
    $type = typeDeclaring([['key' => 'no_type'], ['type' => 'number'], ['key' => 'ok', 'type' => 'text']]);

    expect(resolveAttributes($type, ['no_type' => 'x', 'ok' => 'y']))->toBe(['ok' => 'y']);
});

it('resolves nothing when no type is selected', function (): void {
    expect((new AttributeFilterResolver)->resolve(null, ['anything' => 'value']))->toBe([]);
});
