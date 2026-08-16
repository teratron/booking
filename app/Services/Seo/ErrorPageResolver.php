<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\ErrorPage;
use App\Models\ErrorPageTranslation;
use Illuminate\Support\Facades\Cache;

/**
 * The administrator override for one HTTP status code's error copy — the
 * portal's error views consult this before falling back to their own
 * static translation strings, so a status code nobody has customized yet
 * still renders correctly.
 */
final class ErrorPageResolver
{
    private const string CACHE_KEY_PREFIX = 'error_pages:';

    /**
     * @return array{title: string, body: string}|null null when no
     *                                                 administrator override exists for this status code and language
     */
    public function resolve(int $statusCode, string $locale): ?array
    {
        $cached = $this->cached($statusCode, $locale);

        return $cached === [] ? null : $cached;
    }

    /**
     * Cached as an empty array rather than null on a miss: the cache
     * repository's own `remember` never treats a stored `null` as a hit, so
     * an uncustomized status code would otherwise query on every request
     * instead of just the first.
     *
     * @return array{}|array{title: string, body: string}
     */
    private function cached(int $statusCode, string $locale): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY_PREFIX.$statusCode.'|'.$locale,
            static function () use ($statusCode, $locale): array {
                $errorPage = ErrorPage::query()->where('status_code', $statusCode)->first();

                if (! $errorPage instanceof ErrorPage) {
                    return [];
                }

                /** @var ?ErrorPageTranslation $translation */
                $translation = $errorPage->translate($locale, false);

                if ($translation === null) {
                    return [];
                }

                return ['title' => (string) $translation->title, 'body' => (string) $translation->body];
            },
        );
    }

    /**
     * Drops the cached override for one status code and language — call
     * after any write to {@see ErrorPage} for that pair.
     */
    public function forgetCache(int $statusCode, string $locale): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX.$statusCode.'|'.$locale);
    }
}
