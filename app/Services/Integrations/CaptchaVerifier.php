<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Services\Reviews\ReviewSubmissionService;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a CAPTCHA challenge response against the configured provider —
 * Cloudflare Turnstile, per {@see SettingsRegistry}'s
 * `integrations.captcha_*` settings. The review-submission form's `open`
 * mode ({@see ReviewSubmissionService}) is the first
 * consumer.
 *
 * With no provider configured (`integrations.captcha_provider` = `none` —
 * a fresh clone's own default), every response verifies successfully: there
 * is no challenge to fail, so a form gated by this class stays usable
 * without an administrator visiting the settings screen first, matching how
 * {@see MapTileConfigResolver} degrades rather than blocks.
 */
final class CaptchaVerifier
{
    private const string TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function isEnabled(): bool
    {
        return $this->provider() !== 'none';
    }

    public function siteKey(): string
    {
        return (string) $this->settings->get('integrations.captcha_site_key');
    }

    /**
     * True when no provider is configured (nothing to challenge against),
     * or the provider confirms $response genuine for $clientIp. A network
     * failure or an unrecognised provider fails closed — an anti-abuse
     * control that silently passes on error is not a control.
     */
    public function verify(?string $response, string $clientIp): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($response === null || $response === '') {
            return false;
        }

        return match ($this->provider()) {
            'turnstile' => $this->verifyTurnstile($response, $clientIp),
            default => false,
        };
    }

    private function provider(): string
    {
        return (string) $this->settings->get('integrations.captcha_provider');
    }

    private function verifyTurnstile(string $response, string $clientIp): bool
    {
        $secret = (string) $this->settings->get('integrations.captcha_secret');

        try {
            $result = Http::asForm()->timeout(5)->post(self::TURNSTILE_VERIFY_URL, [
                'secret' => $secret,
                'response' => $response,
                'remoteip' => $clientIp,
            ]);

            return $result->successful() && $result->json('success') === true;
        } catch (Throwable $exception) {
            Log::warning('Turnstile verification request failed.', ['exception' => $exception->getMessage()]);

            return false;
        }
    }
}
