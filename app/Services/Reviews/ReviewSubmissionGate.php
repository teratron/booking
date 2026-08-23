<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Http\Controllers\Public\ContactClickController;
use App\Services\Integrations\CaptchaVerifier;
use App\Services\Settings\SettingsRepository;

/**
 * Decides whether the current visitor may reach a given object's review
 * submission form, per the `reviews.submission_mode` portal setting.
 *
 * In `open` mode every visitor may submit — {@see CaptchaVerifier}
 * is the control there instead. In `contact_gated` mode, only a visitor who
 * has activated at least one contact channel for that object in the current
 * session may submit; {@see self::recordContactClick()} is the sole writer
 * of that session flag, called from {@see ContactClickController}
 * at the point it already records the `contact_click` analytics event — no
 * second, persisted tracking record is added.
 */
final class ReviewSubmissionGate
{
    private const string SESSION_KEY_PREFIX = 'reviews.contact_clicked.';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function mode(): string
    {
        return (string) $this->settings->get('reviews.submission_mode');
    }

    /**
     * Marks the current session as having clicked a contact channel for
     * $objectId — the trigger `contact_gated` mode checks for.
     */
    public function recordContactClick(int $objectId): void
    {
        session()->put(self::SESSION_KEY_PREFIX.$objectId, true);
    }

    /**
     * The server-side enforcement point — the write endpoint calls this
     * itself rather than trusting that the form was reachable, per the
     * spec's own "hiding the form is a usability affordance, not
     * authorization" invariant.
     */
    public function canSubmit(int $objectId): bool
    {
        return match ($this->mode()) {
            'contact_gated' => session()->get(self::SESSION_KEY_PREFIX.$objectId) === true,
            default => true,
        };
    }
}
