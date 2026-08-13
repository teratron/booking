<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Support\Analytics\TrafficSource;
use App\Support\Analytics\TrafficSourceChannel;
use Throwable;

/**
 * First-touch attribution, deliberately coarse per the specification: a
 * channel bucket, a bare referring host, and a campaign tag — never a full
 * referrer URL, and never persisted anywhere beyond the visit's first event
 * row. "First event of a visit" is tracked with one session flag, so every
 * call after the first in the same session returns null and leaves whatever
 * the first event recorded untouched.
 */
final class TrafficSourceRecorder
{
    private const string SESSION_KEY = 'analytics.first_touch_recorded';

    /**
     * @param  ?string  $referrerUrl  the raw `Referer` header, if any — only
     *                                its host survives into the returned value; the rest is
     *                                discarded here and never reaches storage
     * @param  ?string  $campaignTag  a UTM-style campaign tag from the landing
     *                                request, if any
     */
    public function firstTouch(?string $referrerUrl, ?string $campaignTag): ?TrafficSource
    {
        if (! $this->claimFirstTouch()) {
            return null;
        }

        $host = $this->extractHost($referrerUrl);
        $campaign = $campaignTag !== null && $campaignTag !== '' ? $campaignTag : null;

        return new TrafficSource(
            channel: $this->classify($host, $campaign),
            domain: $host,
            campaign: $campaign,
        );
    }

    /**
     * True the first time this is called in the current session; false on
     * every subsequent call. A session that cannot be resolved at all (a
     * console context, a misconfigured store) degrades to "always first"
     * rather than throwing — the caller ({@see EventCaptureService::capture()})
     * must never fail because attribution state is unavailable.
     */
    private function claimFirstTouch(): bool
    {
        if (! app()->bound('session')) {
            return true;
        }

        try {
            if (session()->get(self::SESSION_KEY) === true) {
                return false;
            }

            session()->put(self::SESSION_KEY, true);

            return true;
        } catch (Throwable) {
            return true;
        }
    }

    private function classify(?string $host, ?string $campaign): TrafficSourceChannel
    {
        if ($campaign !== null) {
            return TrafficSourceChannel::Campaign;
        }

        if ($host === null) {
            return TrafficSourceChannel::Direct;
        }

        if ($this->isOwnHost($host)) {
            return TrafficSourceChannel::Internal;
        }

        if ($this->hostMatches($host, self::SEARCH_HOSTS)) {
            return TrafficSourceChannel::Search;
        }

        if ($this->hostMatches($host, self::SOCIAL_HOSTS)) {
            return TrafficSourceChannel::Social;
        }

        return TrafficSourceChannel::Referral;
    }

    /** @var list<string> */
    private const array SEARCH_HOSTS = ['google.', 'bing.', 'yahoo.', 'yandex.', 'duckduckgo.'];

    /** @var list<string> */
    private const array SOCIAL_HOSTS = [
        'facebook.', 'instagram.', 'twitter.', 'x.com', 't.co',
        'tiktok.', 'vk.com', 'linkedin.', 'telegram.org', 't.me',
    ];

    /** @param  list<string>  $needles */
    private function hostMatches(string $host, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isOwnHost(string $host): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && $appHost !== '' && strcasecmp($appHost, $host) === 0;
    }

    /**
     * Host only — scheme, path, query string, and fragment are dropped here
     * and never constructed again. This is the one place a full referrer
     * URL is even briefly in memory.
     */
    private function extractHost(?string $referrerUrl): ?string
    {
        if ($referrerUrl === null || $referrerUrl === '') {
            return null;
        }

        $host = parse_url($referrerUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
