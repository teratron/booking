<?php

declare(strict_types=1);

namespace App\Services\Advertising;

use App\Models\Banner;
use App\Models\BannerSlot;
use App\Models\Language;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Analytics\EventCaptureService;
use Illuminate\Support\Facades\Cache;

/**
 * Selects the single banner eligible to serve one inventory slot for one
 * page render, and records its impression through the analytics capture
 * path. The eligibility pipeline (schedule, language, category, territory)
 * and the ranking it feeds (territory-match specificity first, then display
 * order, with rotation among exact ties) are a fixed contract: every
 * consumer, present or future, must reach the same banner a given request
 * would have gotten from any other consumer.
 *
 * Category and territory context are each optional, independently: a page
 * with no category (the homepage, for one) does not filter on category at
 * all, and — by the same reasoning — a page with no resolved territory does
 * not filter or rank on territory either, so ranking then falls through
 * entirely to display order and rotation. Language context, by contrast, is
 * always expected: every render happens in some interface language, so a
 * language-targeted banner with no language in context simply never
 * matches — the ordinary targeting rule, just never satisfied.
 */
final class BannerSelectionService
{
    private const int CACHE_TTL_SECONDS = 300;

    /** Sorts after every real territory-match depth — "no match at all". */
    private const int UNTARGETED_TERRITORY_SPECIFICITY = PHP_INT_MAX;

    public function __construct(private readonly EventCaptureService $events) {}

    /**
     * Runs the full selection pipeline for $slot and records one
     * `banner_impression` event against the winner. Returns null when the
     * slot collapses — nothing survived every filter — which callers must
     * render as no element at all, never an empty frame.
     *
     * @param  array{
     *     territory?: Territory|int|null,
     *     language?: Language|string|null,
     *     category?: ObjectType|int|null,
     * }  $context  `language`, when a string, is the interface locale code
     */
    public function forSlot(BannerSlot|string $slot, array $context = []): ?Banner
    {
        $slotModel = $this->resolveSlot($slot);

        if (! $slotModel instanceof BannerSlot) {
            return null;
        }

        $territoryId = $this->resolveTerritoryId($context['territory'] ?? null);
        $languageId = $this->resolveLanguageId($context['language'] ?? null);
        $categoryId = $this->resolveCategoryId($context['category'] ?? null);

        $tags = $this->tagsFor((int) $slotModel->id, $territoryId);
        $cacheKey = $this->cacheKey((int) $slotModel->id, $territoryId, $languageId, $categoryId);

        /** @var list<list<int>> $tiers */
        $tiers = Cache::tags($tags)->remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->rankedTiers($slotModel, $territoryId, $languageId, $categoryId),
        );

        $topTier = $tiers[0] ?? [];

        if ($topTier === []) {
            return null;
        }

        $selectedId = $this->rotate($topTier, "{$cacheKey}:rotation", $tags);

        $banner = Banner::query()->with('translations')->find($selectedId);

        if (! $banner instanceof Banner) {
            return null;
        }

        $this->events->capture('banner_impression', $banner, array_filter(
            ['territory_id' => $territoryId],
            static fn (mixed $value): bool => $value !== null,
        ));

        return $banner;
    }

    /**
     * Drops every cached selection for $slotId — every territory/language/
     * category combination together, since each was stored under this same
     * slot tag. Called after any banner create, update, delete, or restore;
     * a flush against a tag nothing has written under yet is a correct
     * no-op.
     */
    public function invalidateSlot(int $slotId): void
    {
        Cache::tags(["slot:{$slotId}"])->flush();
    }

    /**
     * @return list<list<int>> ranked tiers of banner ids — index 0 is the
     *                         best-ranked tier; ids within one tier are exactly tied and rotate
     */
    private function rankedTiers(BannerSlot $slot, ?int $territoryId, ?int $languageId, ?int $categoryId): array
    {
        $candidates = Banner::query()
            ->live()
            ->where('banner_slot_id', $slot->id)
            ->with(['territories', 'categories', 'targetLanguages'])
            ->get();

        $depthByTerritoryId = $territoryId !== null ? $this->ancestorDepths($territoryId) : [];

        $scored = [];

        /** @var Banner $banner */
        foreach ($candidates as $banner) {
            if (! $this->passesLanguage($banner, $languageId)) {
                continue;
            }

            if (! $this->passesCategory($banner, $categoryId)) {
                continue;
            }

            $specificity = $this->territorySpecificity($banner, $territoryId, $depthByTerritoryId);

            if ($specificity === null) {
                continue;
            }

            $scored[] = [
                'id' => (int) $banner->id,
                'specificity' => $specificity,
                'display_order' => (int) $banner->display_order,
            ];
        }

        usort(
            $scored,
            static fn (array $left, array $right): int => [$left['specificity'], $left['display_order']]
                <=> [$right['specificity'], $right['display_order']],
        );

        return $this->groupIntoTiers($scored);
    }

    /**
     * @param  list<array{id: int, specificity: int, display_order: int}>  $scored  pre-sorted by [specificity, display_order]
     * @return list<list<int>>
     */
    private function groupIntoTiers(array $scored): array
    {
        $tiers = [];

        foreach ($scored as $row) {
            $lastIndex = array_key_last($tiers);

            if ($lastIndex !== null
                && $tiers[$lastIndex]['specificity'] === $row['specificity']
                && $tiers[$lastIndex]['display_order'] === $row['display_order']) {
                $tiers[$lastIndex]['ids'][] = $row['id'];

                continue;
            }

            $tiers[] = [
                'specificity' => $row['specificity'],
                'display_order' => $row['display_order'],
                'ids' => [$row['id']],
            ];
        }

        return array_map(static fn (array $tier): array => $tier['ids'], $tiers);
    }

    private function passesLanguage(Banner $banner, ?int $languageId): bool
    {
        if ($banner->targetLanguages->isEmpty()) {
            return true;
        }

        return $languageId !== null && $banner->targetLanguages->contains('id', $languageId);
    }

    private function passesCategory(Banner $banner, ?int $categoryId): bool
    {
        if ($categoryId === null) {
            return true;
        }

        if ($banner->categories->isEmpty()) {
            return true;
        }

        return $banner->categories->contains('id', $categoryId);
    }

    /**
     * Null return means "excluded" (targeted, but none of its territories
     * are the requested one or an ancestor of it) — distinct from 0, a
     * genuine best-possible match.
     *
     * @param  array<int, int>  $depthByTerritoryId
     */
    private function territorySpecificity(Banner $banner, ?int $territoryId, array $depthByTerritoryId): ?int
    {
        if ($territoryId === null) {
            // No page territory resolved: the dimension cannot filter or
            // rank, so every candidate ties on it — the same treatment the
            // category dimension gets when the page supplies no category.
            return 0;
        }

        $targetIds = $banner->territories->pluck('id');

        if ($targetIds->isEmpty()) {
            return self::UNTARGETED_TERRITORY_SPECIFICITY;
        }

        $matchedDepths = $targetIds
            ->map(static fn (mixed $id): ?int => $depthByTerritoryId[(int) $id] ?? null)
            ->filter(static fn (?int $depth): bool => $depth !== null);

        if ($matchedDepths->isEmpty()) {
            return null;
        }

        return (int) $matchedDepths->min();
    }

    /**
     * @return array<int, int> territory id => distance from the requested
     *                         territory (0 = itself, increasing toward the root)
     */
    private function ancestorDepths(int $territoryId): array
    {
        $territory = Territory::query()->find($territoryId);

        if (! $territory instanceof Territory) {
            return [];
        }

        // The adjacency-list package's own `depth` column runs the other
        // way for this relation — 0 at the requested territory, negative
        // going up toward the root — so it is negated here into a plain
        // "distance from self" a caller can compare with min().
        /** @var array<int, int> */
        return $territory->ancestorsAndSelf()
            ->get()
            ->mapWithKeys(static fn (Territory $node): array => [
                (int) $node->id => -1 * (int) $node->getAttribute($node->getDepthName()),
            ])
            ->all();
    }

    private function resolveSlot(BannerSlot|string $slot): ?BannerSlot
    {
        if ($slot instanceof BannerSlot) {
            return $slot;
        }

        return BannerSlot::query()->where('key', $slot)->first();
    }

    private function resolveTerritoryId(Territory|int|null $territory): ?int
    {
        return $territory instanceof Territory ? (int) $territory->id : $territory;
    }

    private function resolveCategoryId(ObjectType|int|null $category): ?int
    {
        return $category instanceof ObjectType ? (int) $category->id : $category;
    }

    private function resolveLanguageId(Language|string|null $language): ?int
    {
        if ($language instanceof Language) {
            return (int) $language->id;
        }

        if ($language === null) {
            return null;
        }

        $id = Language::query()->where('code', $language)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return list<string>
     */
    private function tagsFor(int $slotId, ?int $territoryId): array
    {
        $tags = ['banners', "slot:{$slotId}"];

        if ($territoryId !== null) {
            $tags[] = "territory:{$territoryId}";
        }

        return $tags;
    }

    private function cacheKey(int $slotId, ?int $territoryId, ?int $languageId, ?int $categoryId): string
    {
        return sprintf(
            'banner-selection:%d:%s:%s:%s',
            $slotId,
            $territoryId ?? 'none',
            $languageId ?? 'none',
            $categoryId ?? 'none',
        );
    }

    /**
     * Round-robins through $ids across successive calls sharing
     * $rotationKey — the cursor lives under the same $tags as the ranked
     * tiers it serves, so invalidating the selection resets the cursor
     * along with everything else instead of leaving it pointed at a banner
     * id that may no longer be in the tied group.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $tags
     */
    private function rotate(array $ids, string $rotationKey, array $tags): int
    {
        $count = count($ids);

        if ($count === 1) {
            return $ids[0];
        }

        $cursor = (int) Cache::tags($tags)->get($rotationKey, 0);
        Cache::tags($tags)->forever($rotationKey, ($cursor + 1) % $count);

        return $ids[$cursor % $count];
    }
}
