<?php

declare(strict_types=1);

namespace App\Services\ErrorTracking;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

/**
 * Sentry's `before_send` hook (wired in `config/sentry.php`): the last point
 * before an event leaves this application for the error-tracking service,
 * and the place the third-party integrations specification names for
 * scrubbing personal data out of an error report. Owner contact details —
 * phone numbers, emails — reach a failed job or a failed request through the
 * record being processed, and none of that may leave the application even
 * though the failure itself must be visible.
 *
 * Two mechanisms, deliberately layered:
 *
 * - Key-name redaction: any array key across the event's request payload,
 *   extra data, named contexts, and breadcrumb metadata that *looks* like a
 *   personal-data field (`phone`, `email`, `passport`, …) is replaced
 *   outright, regardless of its value's shape. This is the reliable
 *   guarantee — the caller who attaches an owner's phone number as
 *   structured context under a plainly named key never has to trust a
 *   regular expression to catch it.
 * - Value-shape redaction: free text (an exception message, an unlabelled
 *   string value) is additionally scanned for phone-number- and
 *   email-shaped substrings. This is best-effort defense in depth for the
 *   case a value was not attached under a recognisable key, and it may over
 *   match on other digit-heavy text — an acceptable trade for personal data,
 *   where a false positive costs a redacted diagnostic and a false negative
 *   costs a leak.
 */
final class ScrubsPersonalDataBeforeSend
{
    private const string REDACTED = '[redacted]';

    /** Substrings, matched case-insensitively, that mark a key as personal data. */
    private const array SENSITIVE_KEY_NEEDLES = [
        'phone', 'telephone', 'mobile', 'whatsapp', 'viber', 'telegram',
        'email', 'e-mail', 'password', 'secret', 'token', 'passport',
        'iban', 'card_number', 'address',
    ];

    /** Digit runs long enough, with phone-number-shaped separators, to plausibly be a telephone number. */
    private const string PHONE_PATTERN = '/(?<![\w@.])\+?\d[\d\s().-]{6,}\d(?![\w@.])/';

    private const string EMAIL_PATTERN = '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i';

    public static function handle(Event $event, ?EventHint $hint): Event
    {
        $event->setRequest(self::scrubArray($event->getRequest()));
        $event->setExtra(self::scrubArray($event->getExtra()));

        foreach ($event->getContexts() as $name => $data) {
            $event->setContext($name, self::scrubArray($data));
        }

        $message = $event->getMessage();

        if ($message !== null) {
            $event->setMessage(self::scrubString($message), $event->getMessageParams(), $event->getMessageFormatted());
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::scrubString($exception->getValue()));
        }

        $event->setBreadcrumb(array_map(self::scrubBreadcrumb(...), $event->getBreadcrumbs()));

        return $event;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function scrubArray(array $data): array
    {
        $scrubbed = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::keyLooksSensitive($key)) {
                $scrubbed[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $scrubbed[$key] = self::scrubArray($value);

                continue;
            }

            $scrubbed[$key] = is_string($value) ? self::scrubString($value) : $value;
        }

        return $scrubbed;
    }

    private static function scrubBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        foreach ($breadcrumb->getMetadata() as $key => $value) {
            $breadcrumb = $breadcrumb->withMetadata(
                $key,
                self::keyLooksSensitive($key)
                    ? self::REDACTED
                    : (is_string($value) ? self::scrubString($value) : $value),
            );
        }

        $message = $breadcrumb->getMessage();

        return $message !== null ? $breadcrumb->withMessage(self::scrubString($message)) : $breadcrumb;
    }

    private static function keyLooksSensitive(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEY_NEEDLES as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function scrubString(string $value): string
    {
        $value = preg_replace(self::PHONE_PATTERN, self::REDACTED, $value) ?? $value;

        return preg_replace(self::EMAIL_PATTERN, self::REDACTED, $value) ?? $value;
    }
}
