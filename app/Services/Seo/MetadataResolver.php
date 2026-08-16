<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\NewsItem;
use App\Models\NewsTranslation;
use App\Models\Object_;
use App\Models\ObjectTranslation;
use App\Models\ObjectType;
use App\Models\ObjectTypeTranslation;
use App\Models\Promotion;
use App\Models\PromotionTranslation;
use App\Models\SeoMetadataTemplate;
use App\Models\Territory;
use App\Models\TerritoryTranslation;
use App\Services\Settings\SettingsRepository;
use App\Support\Seo\ResolvedMetadata;
use App\Support\Seo\SeoEntityContext;
use App\Support\Seo\SeoEntityType;
use App\Support\Seo\SeoMetadataField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * The three-rung metadata resolution ladder every indexable public page
 * reads: an entity's own explicit `seo_*` translation column, else an
 * administrator-defined {@see SeoMetadataTemplate} rendered from entity
 * data, else a plain derivation from the entity's name and its territory.
 * Guarantees a non-empty title and description — the portal's own
 * no-empty-metadata rule — and mirrors both into Open Graph title and
 * description when no explicit Open Graph override exists.
 */
final class MetadataResolver
{
    private const string CACHE_KEY_PREFIX = 'seo_metadata_templates:';

    /** @var array<string, array<string, string>> entity_type|locale => [field => template] */
    private array $templateCache = [];

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function resolve(Model $entity, string $locale, ?string $selfUrl = null): ResolvedMetadata
    {
        $context = $this->contextFor($entity, $locale);

        $title = $this->resolveField($context->explicitTitle, $context->type, SeoMetadataField::Title, $locale, $context->name, $context->territoryName);
        $description = $this->resolveField($context->explicitDescription, $context->type, SeoMetadataField::Description, $locale, $context->name, $context->territoryName);

        return new ResolvedMetadata(
            title: $title,
            description: $description,
            canonicalUrl: $this->nonEmpty($context->explicitCanonicalUrl) ?? $selfUrl,
            indexable: $context->explicitIndexable ?? true,
            ogTitle: $this->nonEmpty($context->explicitOgTitle) ?? $title,
            ogDescription: $this->nonEmpty($context->explicitOgDescription) ?? $description,
            ogImageUrl: $this->nonEmpty($context->explicitOgImage) ?? $context->ogImage ?? $this->defaultOgImage(),
        );
    }

    /**
     * The typed-catalog page (`{settlement}/{type}`) is not one entity but a
     * composite of a territory and an object type, so it has no explicit
     * override of its own — promoting a specific combination is the
     * indexation allowlist's job, not this ladder's. Title and description
     * render from the object type's own template (the type's only real
     * consumer, since it has no standalone page) with both entities' names,
     * or fall back to a plain derivation from both.
     */
    public function resolveTypedCatalog(Territory $territory, ObjectType $objectType, string $locale, ?string $selfUrl = null): ResolvedMetadata
    {
        $typeName = $this->fallbackName($objectType, $locale);
        $territoryName = $this->fallbackName($territory, $locale);

        $title = $this->render(SeoEntityType::ObjectType, SeoMetadataField::Title, $locale, $typeName, $territoryName)
            ?? $this->derive(SeoMetadataField::Title, $locale, $typeName, $territoryName);

        $description = $this->render(SeoEntityType::ObjectType, SeoMetadataField::Description, $locale, $typeName, $territoryName)
            ?? $this->derive(SeoMetadataField::Description, $locale, $typeName, $territoryName);

        $ogImage = $this->nonEmpty($territory->hero_image_path !== null ? Storage::url($territory->hero_image_path) : null)
            ?? $this->defaultOgImage();

        return new ResolvedMetadata(
            title: $title,
            description: $description,
            canonicalUrl: $selfUrl,
            indexable: true,
            ogTitle: $title,
            ogDescription: $description,
            ogImageUrl: $ogImage,
        );
    }

    /**
     * The interactive `/catalog` search page is neither one entity nor a
     * fixed territory-and-type composite — its title reflects whichever of
     * a selected object type and territory the visitor's own filters name,
     * degrading to a plain portal-wide catalog title when neither is set.
     * `$indexable` is not decided here: {@see IndexationPolicy} owns that
     * decision, since it depends on the full active filter set, not just
     * these two optional dimensions.
     */
    public function resolveCatalog(?ObjectType $objectType, ?Territory $territory, bool $indexable, string $locale, ?string $selfUrl = null): ResolvedMetadata
    {
        $typeName = $objectType instanceof ObjectType ? $this->fallbackName($objectType, $locale) : null;
        $territoryName = $territory instanceof Territory ? $this->fallbackName($territory, $locale) : null;

        $title = match (true) {
            $typeName !== null && $territoryName !== null => "{$typeName} — {$territoryName}",
            $typeName !== null => $typeName,
            $territoryName !== null => trim((string) __('public.seo.catalog_in_territory', ['territory' => $territoryName], $locale)),
            default => trim((string) __('public.seo.catalog_default_title', [], $locale)),
        };

        $description = trim((string) __('public.seo.catalog_default_description', [], $locale));
        $ogImage = $this->defaultOgImage();

        return new ResolvedMetadata(
            title: $title,
            description: $description,
            canonicalUrl: $selfUrl,
            indexable: $indexable,
            ogTitle: $title,
            ogDescription: $description,
            ogImageUrl: $ogImage,
        );
    }

    private function resolveField(?string $explicit, SeoEntityType $type, SeoMetadataField $field, string $locale, string $name, ?string $territoryName): string
    {
        return $this->nonEmpty($explicit)
            ?? $this->render($type, $field, $locale, $name, $territoryName)
            ?? $this->derive($field, $locale, $name, $territoryName);
    }

    private function render(SeoEntityType $type, SeoMetadataField $field, string $locale, string $name, ?string $territoryName): ?string
    {
        $template = $this->templatesFor($type, $locale)[$field->value] ?? null;

        if ($template === null || $template === '') {
            return null;
        }

        return $this->nonEmpty(strtr($template, ['{name}' => $name, '{territory}' => $territoryName ?? '']));
    }

    private function derive(SeoMetadataField $field, string $locale, string $name, ?string $territoryName): string
    {
        if ($field === SeoMetadataField::Title) {
            return $territoryName !== null && $territoryName !== '' ? "{$name} — {$territoryName}" : $name;
        }

        return trim((string) __('public.seo.derived_description', ['name' => $name], $locale));
    }

    /**
     * Cached indefinitely, not just memoized per request: a territory page
     * alone is already at its 30-query performance budget, and templates
     * change by administrator action (rare) rather than by request, so
     * paying a database round trip on every cold request is the wrong
     * trade. {@see self::forgetTemplateCache()} is the drop side of this
     * cache, for the administration screen that will write to this table.
     *
     * @return array<string, string>
     */
    private function templatesFor(SeoEntityType $type, string $locale): array
    {
        $key = $type->value.'|'.$locale;

        if (isset($this->templateCache[$key])) {
            return $this->templateCache[$key];
        }

        return $this->templateCache[$key] = Cache::rememberForever(
            self::CACHE_KEY_PREFIX.$key,
            static function () use ($type, $locale): array {
                $result = [];

                foreach (SeoMetadataTemplate::query()->where('entity_type', $type->value)->where('locale', $locale)->get(['field', 'template']) as $row) {
                    $result[(string) $row->getAttribute('field')] = (string) $row->getAttribute('template');
                }

                return $result;
            },
        );
    }

    /**
     * Drops the cached template set for one entity kind and language —
     * call after any write to {@see SeoMetadataTemplate} for that pair.
     */
    public function forgetTemplateCache(SeoEntityType $type, string $locale): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX.$type->value.'|'.$locale);
    }

    /**
     * Populates the cache for one entity kind and language ahead of the
     * first real request — templates change by rare administrator action,
     * not by traffic, so this belongs alongside the portal's other
     * rarely-changing, shared lookups (active languages, navigation
     * groups) that a deployment warms once rather than every visitor's
     * first request paying for individually.
     */
    public function warmTemplateCache(SeoEntityType $type, string $locale): void
    {
        $this->templatesFor($type, $locale);
    }

    private function defaultOgImage(): ?string
    {
        return $this->nonEmpty((string) $this->settings->get('seo.default_og_image'));
    }

    private function nonEmpty(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function contextFor(Model $entity, string $locale): SeoEntityContext
    {
        return match (true) {
            $entity instanceof Territory => $this->territoryContext($entity, $locale),
            $entity instanceof ObjectType => $this->objectTypeContext($entity, $locale),
            $entity instanceof Object_ => $this->objectContext($entity, $locale),
            $entity instanceof Promotion => $this->promotionContext($entity, $locale),
            $entity instanceof NewsItem => $this->newsItemContext($entity, $locale),
            $entity instanceof Article => $this->articleContext($entity, $locale),
            default => throw new InvalidArgumentException('Unsupported SEO metadata entity: '.$entity::class),
        };
    }

    private function territoryContext(Territory $territory, string $locale): SeoEntityContext
    {
        /** @var ?TerritoryTranslation $translation */
        $translation = $territory->translate($locale, false);

        return new SeoEntityContext(
            type: SeoEntityType::Territory,
            name: $this->fallbackName($territory, $locale),
            territoryName: null,
            ogImage: $territory->hero_image_path !== null ? Storage::url($territory->hero_image_path) : null,
            explicitTitle: $translation?->seo_title,
            explicitDescription: $translation?->seo_description,
            explicitCanonicalUrl: $translation?->seo_canonical_url,
            explicitIndexable: $translation?->seo_indexable,
            explicitOgTitle: $translation?->seo_og_title,
            explicitOgDescription: $translation?->seo_og_description,
            explicitOgImage: $translation?->seo_og_image,
        );
    }

    private function objectTypeContext(ObjectType $objectType, string $locale): SeoEntityContext
    {
        /** @var ?ObjectTypeTranslation $translation */
        $translation = $objectType->translate($locale, false);

        return new SeoEntityContext(
            type: SeoEntityType::ObjectType,
            name: $this->fallbackName($objectType, $locale),
            territoryName: null,
            ogImage: null,
            explicitTitle: $translation?->seo_title,
            explicitDescription: $translation?->seo_description,
            explicitCanonicalUrl: $translation?->seo_canonical_url,
            explicitIndexable: $translation?->seo_indexable,
            explicitOgTitle: $translation?->seo_og_title,
            explicitOgDescription: $translation?->seo_og_description,
            explicitOgImage: $translation?->seo_og_image,
        );
    }

    private function objectContext(Object_ $object, string $locale): SeoEntityContext
    {
        /** @var ?ObjectTranslation $translation */
        $translation = $object->translate($locale, false);
        $territory = $object->territory;

        return new SeoEntityContext(
            type: SeoEntityType::Object_,
            name: $this->fallbackName($object, $locale),
            territoryName: $territory instanceof Territory ? $this->fallbackName($territory, $locale) : null,
            ogImage: $object->getFirstMediaUrl('photos') ?: null,
            explicitTitle: $translation?->seo_title,
            explicitDescription: $translation?->seo_description,
            explicitCanonicalUrl: $translation?->seo_canonical_url,
            explicitIndexable: $translation?->seo_indexable,
            explicitOgTitle: $translation?->seo_og_title,
            explicitOgDescription: $translation?->seo_og_description,
            explicitOgImage: $translation?->seo_og_image,
        );
    }

    private function promotionContext(Promotion $promotion, string $locale): SeoEntityContext
    {
        /** @var ?PromotionTranslation $translation */
        $translation = $promotion->translate($locale, false);
        $territory = $promotion->territory;

        return new SeoEntityContext(
            type: SeoEntityType::Promotion,
            name: $this->fallbackTitle($promotion, $locale),
            territoryName: $territory instanceof Territory ? $this->fallbackName($territory, $locale) : null,
            ogImage: $promotion->getFirstMediaUrl('image') ?: null,
            explicitTitle: $translation?->seo_title,
            explicitDescription: $translation?->seo_description,
            explicitCanonicalUrl: $translation?->seo_canonical_url,
            explicitIndexable: $translation?->seo_indexable,
            explicitOgTitle: $translation?->seo_og_title,
            explicitOgDescription: $translation?->seo_og_description,
            explicitOgImage: $translation?->seo_og_image,
        );
    }

    private function newsItemContext(NewsItem $newsItem, string $locale): SeoEntityContext
    {
        /** @var ?NewsTranslation $translation */
        $translation = $newsItem->translate($locale, false);
        $territory = $newsItem->territory;

        return new SeoEntityContext(
            type: SeoEntityType::NewsItem,
            name: $this->fallbackTitle($newsItem, $locale),
            territoryName: $territory instanceof Territory ? $this->fallbackName($territory, $locale) : null,
            ogImage: $newsItem->getFirstMediaUrl('cover_image') ?: null,
            explicitTitle: $translation?->seo_title,
            explicitDescription: $translation?->seo_description,
            explicitCanonicalUrl: $translation?->seo_canonical_url,
            explicitIndexable: $translation?->seo_indexable,
            explicitOgTitle: $translation?->seo_og_title,
            explicitOgDescription: $translation?->seo_og_description,
            explicitOgImage: $translation?->seo_og_image,
        );
    }

    private function articleContext(Article $article, string $locale): SeoEntityContext
    {
        /** @var ?ArticleTranslation $translation */
        $translation = $article->translate($locale, false);
        $territory = $article->territories->first();

        return new SeoEntityContext(
            type: SeoEntityType::Article,
            name: $this->fallbackTitle($article, $locale),
            territoryName: $territory instanceof Territory ? $this->fallbackName($territory, $locale) : null,
            ogImage: $article->getFirstMediaUrl('cover_image') ?: null,
            explicitTitle: $translation?->seo_title,
            explicitDescription: $translation?->seo_description,
            explicitCanonicalUrl: $translation?->seo_canonical_url,
            explicitIndexable: $translation?->seo_indexable,
            explicitOgTitle: $translation?->seo_og_title,
            explicitOgDescription: $translation?->seo_og_description,
            explicitOgImage: $translation?->seo_og_image,
        );
    }

    /** The `name`-bearing entities: fallback-allowed resolution of their own display name. */
    private function fallbackName(Territory|ObjectType|Object_ $entity, string $locale): string
    {
        if ($entity instanceof Territory) {
            /** @var TerritoryTranslation $translation */
            $translation = $entity->translate($locale, true);

            return (string) $translation->name;
        }

        if ($entity instanceof ObjectType) {
            /** @var ObjectTypeTranslation $translation */
            $translation = $entity->translate($locale, true);

            return (string) $translation->name;
        }

        /** @var ObjectTranslation $translation */
        $translation = $entity->translate($locale, true);

        return (string) $translation->name;
    }

    /** The `title`-bearing content entities: fallback-allowed resolution of their own display title. */
    private function fallbackTitle(Promotion|NewsItem|Article $entity, string $locale): string
    {
        if ($entity instanceof Promotion) {
            /** @var PromotionTranslation $translation */
            $translation = $entity->translate($locale, true);

            return (string) $translation->title;
        }

        if ($entity instanceof NewsItem) {
            /** @var NewsTranslation $translation */
            $translation = $entity->translate($locale, true);

            return (string) $translation->title;
        }

        /** @var ArticleTranslation $translation */
        $translation = $entity->translate($locale, true);

        return (string) $translation->title;
    }
}
