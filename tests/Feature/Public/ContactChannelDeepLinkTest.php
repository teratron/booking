<?php

declare(strict_types=1);

use App\Models\ContactChannelType;
use App\Services\Contact\ContactChannelLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Contact Channel Deep-Link Resolution
|--------------------------------------------------------------------------
|
| The type registry's own link_template turns an owner's raw stored value
| into an actionable deep link — the resolver carries no per-channel
| branching, so this suite proves generic substitution against several real
| template shapes and against a channel type invented purely for this test,
| never named anywhere in the resolver itself.
|
*/

function contactChannelDeepLinkMakeType(string $key, ?string $template, bool $isActive = true): ContactChannelType
{
    $id = DB::table('contact_channel_types')->insertGetId([
        'key' => $key, 'link_template' => $template, 'is_active' => $isActive,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var ContactChannelType $type */
    $type = ContactChannelType::query()->findOrFail($id);

    return $type;
}

it("resolves a raw value into the correct actionable link, purely from the type's own template", function (): void {
    $phone = contactChannelDeepLinkMakeType('phone', 'tel:{value}');
    $whatsapp = contactChannelDeepLinkMakeType('whatsapp', 'https://wa.me/{value}');
    $viber = contactChannelDeepLinkMakeType('viber', 'viber://chat?number={value}');
    $website = contactChannelDeepLinkMakeType('website', '{value}');

    $resolver = app(ContactChannelLinkResolver::class);

    expect($resolver->resolve($phone, '+37360000000'))->toBe('tel:+37360000000')
        ->and($resolver->resolve($whatsapp, '37360000000'))->toBe('https://wa.me/37360000000')
        ->and($resolver->resolve($viber, '37360000000'))->toBe('viber://chat?number=37360000000')
        ->and($resolver->resolve($website, 'https://example.test'))->toBe('https://example.test');
});

it('returns null for an inactive channel type, never a broken link', function (): void {
    $type = contactChannelDeepLinkMakeType('retired', 'https://retired.test/{value}', isActive: false);

    expect(app(ContactChannelLinkResolver::class)->resolve($type, 'handle'))->toBeNull();
});

it('returns null when the type carries no template, rather than fabricating one', function (): void {
    $type = contactChannelDeepLinkMakeType('untemplated', null);

    expect(app(ContactChannelLinkResolver::class)->resolve($type, 'handle'))->toBeNull();
});

it('resolves a channel type registered purely as a data row, with no matching branch in the resolver itself', function (): void {
    // "snapchat" names nothing in ContactChannelLinkResolver — its own
    // source has no per-key branch to update. If this resolves correctly,
    // the substitution is genuinely generic.
    $snapchat = contactChannelDeepLinkMakeType('snapchat', 'https://snapchat.com/add/{value}');

    expect(app(ContactChannelLinkResolver::class)->resolve($snapchat, 'the-owners-handle'))
        ->toBe('https://snapchat.com/add/the-owners-handle');
});
