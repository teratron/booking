<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\Object_;
use App\Models\ObjectTranslation;
use App\Services\Localization\LanguageRegistry;

/**
 * Whether an object carries everything the cabinet treats as required before
 * it may move beyond draft. Declared type, country, and territory are
 * deliberately not checked here — each is a `NOT NULL` foreign key at the
 * database level, assigned once at staff provisioning, so an object reaching
 * the cabinet at all already satisfies them. What remains genuinely
 * optional at that point, and what this checks, is coordinates (required
 * here though the admin form does not require them) and the primary-language
 * name, without which a listing has nothing to show a visitor at all.
 */
final class ObjectPublicationEligibilityService
{
    public function __construct(private readonly LanguageRegistry $languages) {}

    public function isEligibleForPublication(Object_ $object): bool
    {
        return $this->missingRequirements($object) === [];
    }

    /**
     * The requirement keys $object still fails, in a fixed, deterministic
     * order — each corresponds to a `panel.cabinet.objects.eligibility.*`
     * translation key a caller can render directly. Empty means eligible.
     *
     * @return list<string>
     */
    public function missingRequirements(Object_ $object): array
    {
        $missing = [];

        if ($object->latitude === null || $object->longitude === null) {
            $missing[] = 'coordinates';
        }

        $primaryLocale = $this->languages->primaryLocale();

        /** @var ?ObjectTranslation $translation */
        $translation = $object->translations->firstWhere('locale', $primaryLocale);

        if (blank($translation?->name)) {
            $missing[] = 'name';
        }

        return $missing;
    }
}
