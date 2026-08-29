<?php

declare(strict_types=1);

use App\Services\ErrorTracking\ScrubsPersonalDataBeforeSend;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;

/*
|--------------------------------------------------------------------------
| Sentry Personal Data Scrubber
|--------------------------------------------------------------------------
|
| Exercises the before_send hook directly against Sentry's own value
| objects — no bound hub, no network call. Every surface the hook touches
| (request, extra, named contexts, the top-level message, exception
| values, and breadcrumb metadata/messages) is proven to redact what it
| claims to and to leave everything else exactly as it arrived.
|
*/

test('redacts a top-level sensitive key in the request payload regardless of letter case', function (): void {
    $event = Event::createEvent();
    $event->setRequest([
        'Email' => 'owner@example.com',
        'PHONE_NUMBER' => '+373 69 123 456',
        'note' => 'not sensitive',
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getRequest())->toBe([
        'Email' => '[redacted]',
        'PHONE_NUMBER' => '[redacted]',
        'note' => 'not sensitive',
    ]);
});

test('redacts a sensitive key outright even when its value is a nested array', function (): void {
    // Regression: the class's own docblock promises key-name redaction
    // "regardless of its value's shape". The original implementation checked
    // is_array() before the sensitive-key check, so a sensitive key holding
    // an array value skipped straight to recursion instead of being blanked
    // — a passport number nested under non-obvious child keys would survive.
    $event = Event::createEvent();
    $event->setExtra([
        'passport' => ['number' => 'A1234567', 'issued_by' => 'MD'],
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getExtra())->toBe([
        'passport' => '[redacted]',
    ]);
});

test('recurses into a non-sensitive nested array and still redacts sensitive keys found inside it', function (): void {
    $event = Event::createEvent();
    $event->setExtra([
        'owner' => [
            'email' => 'owner@example.com',
            'display_name' => 'Ana',
        ],
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getExtra())->toBe([
        'owner' => [
            'email' => '[redacted]',
            'display_name' => 'Ana',
        ],
    ]);
});

test('scrubs phone- and email-shaped substrings out of free text under a non-sensitive key', function (): void {
    $event = Event::createEvent();
    $event->setRequest([
        'note' => 'Call +373 69 123 456 or write to owner@example.com for details.',
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getRequest())->toBe([
        'note' => 'Call [redacted] or write to [redacted] for details.',
    ]);
});

test('leaves non-string, non-array values under non-sensitive keys completely untouched', function (): void {
    $event = Event::createEvent();
    $event->setExtra([
        'retry_count' => 3,
        'is_verified' => true,
        'ratio' => 0.5,
        'missing' => null,
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getExtra())->toBe([
        'retry_count' => 3,
        'is_verified' => true,
        'ratio' => 0.5,
        'missing' => null,
    ]);
});

test('scrubs every named context, redacting sensitive keys while leaving the rest intact', function (): void {
    $event = Event::createEvent();
    $event->setContext('owner', ['phone' => '+373 69 123 456', 'country' => 'Moldova']);
    $event->setContext('object', ['category' => 'apartment', 'territory' => 'Chisinau']);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getContexts())->toBe([
        'owner' => ['phone' => '[redacted]', 'country' => 'Moldova'],
        'object' => ['category' => 'apartment', 'territory' => 'Chisinau'],
    ]);
});

test('scrubs a phone number out of the top-level event message while preserving its params and formatted text', function (): void {
    $event = Event::createEvent();
    $event->setMessage(
        'Failed to notify owner at +373 69 123 456',
        ['owner_id' => '42'],
        'Failed to notify owner at +373 69 123 456',
    );

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getMessage())->toBe('Failed to notify owner at [redacted]')
        ->and($result->getMessageParams())->toBe(['owner_id' => '42'])
        ->and($result->getMessageFormatted())->toBe('Failed to notify owner at +373 69 123 456');
});

test('leaves the event message untouched when the event carries none', function (): void {
    $event = Event::createEvent();

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getMessage())->toBeNull();
});

test('scrubs the value of every exception attached to the event', function (): void {
    $event = Event::createEvent();
    $event->setExceptions([
        new ExceptionDataBag(new RuntimeException('Notify owner@example.com about the failure')),
        new ExceptionDataBag(new RuntimeException('Call +373 69 123 456 back')),
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    $values = array_map(
        static fn (ExceptionDataBag $exception): string => $exception->getValue(),
        $result->getExceptions(),
    );

    expect($values)->toBe([
        'Notify [redacted] about the failure',
        'Call [redacted] back',
    ]);
});

test('redacts a sensitive breadcrumb metadata key while leaving sibling metadata alone', function (): void {
    $event = Event::createEvent();
    $event->setBreadcrumb([
        new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'notification', null, [
            'email' => 'owner@example.com',
            'channel' => 'sms',
        ]),
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getBreadcrumbs()[0]->getMetadata())->toBe([
        'email' => '[redacted]',
        'channel' => 'sms',
    ]);
});

test('scrubs phone-shaped text in non-sensitive breadcrumb metadata and leaves non-string metadata alone', function (): void {
    $event = Event::createEvent();
    $event->setBreadcrumb([
        new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'notification', null, [
            'note' => 'Reached owner at +373 69 123 456',
            'attempt' => 2,
        ]),
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);

    expect($result->getBreadcrumbs()[0]->getMetadata())->toBe([
        'note' => 'Reached owner at [redacted]',
        'attempt' => 2,
    ]);
});

test('scrubs a breadcrumb message and leaves a breadcrumb without one untouched', function (): void {
    $event = Event::createEvent();
    $event->setBreadcrumb([
        new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'notification', 'Contact owner@example.com now'),
        new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'notification'),
    ]);

    $result = ScrubsPersonalDataBeforeSend::handle($event, null);
    $breadcrumbs = $result->getBreadcrumbs();

    expect($breadcrumbs[0]->getMessage())->toBe('Contact [redacted] now')
        ->and($breadcrumbs[1]->getMessage())->toBeNull();
});
