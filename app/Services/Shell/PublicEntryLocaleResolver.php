<?php

declare(strict_types=1);

namespace App\Services\Shell;

use App\Http\Middleware\ResolvePublicLocale;
use App\Services\Localization\LanguageRegistry;
use App\Support\Shell\PublicLanguageOption;
use Illuminate\Http\Request;

/**
 * Picks the language a visitor who arrived without one should be sent to.
 *
 * Every public page lives under a `{lang}` segment that
 * {@see ResolvePublicLocale} treats as authoritative, which leaves exactly
 * one address with no language to read: the site root. This resolver is
 * that address's answer — negotiate the visitor's own `Accept-Language`
 * against the active language registry, and fall back to the primary
 * language when nothing matches.
 *
 * Never hard-codes a language or a count: both the candidate set and the
 * fallback come from the registry, so activating a language in the admin
 * panel changes what this returns with no code change.
 */
final class PublicEntryLocaleResolver
{
    public function __construct(
        private readonly PublicShellDataProvider $shellData,
        private readonly LanguageRegistry $languages,
    ) {}

    /**
     * The active language code best matching $request, always one the
     * registry currently has active.
     */
    public function resolve(Request $request): string
    {
        $active = array_map(
            static fn (PublicLanguageOption $language): string => $language->code,
            $this->shellData->activeLanguages(),
        );

        if ($active === []) {
            // The registry is empty or unreachable (a fresh database, a
            // deployment mid-seed). The static fallback is the only answer
            // left, and returning it is still better than a 500 at the
            // portal's front door.
            return (string) $this->languages->primaryLocale();
        }

        $preferred = $this->preferredFirst($active);

        // Symfony returns the first candidate when the header matches none
        // of them — so ordering the primary language first makes the
        // no-match fallback the primary language, with no second branch.
        return (string) $request->getPreferredLanguage($preferred);
    }

    /**
     * $active reordered so the primary language leads.
     *
     * @param  list<string>  $active
     * @return list<string>
     */
    private function preferredFirst(array $active): array
    {
        $primary = $this->languages->primaryLocale();

        if (! in_array($primary, $active, true)) {
            return $active;
        }

        return [$primary, ...array_values(array_filter($active, static fn (string $code): bool => $code !== $primary))];
    }
}
