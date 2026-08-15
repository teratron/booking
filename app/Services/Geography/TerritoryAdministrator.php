<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Exceptions\SlugReuseRefusedException;
use App\Exceptions\TerritoryCycleException;
use App\Models\Country;
use App\Models\Territory;
use App\Models\TerritoryTranslation;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Seo\RedirectRegistrar;
use Illuminate\Support\Facades\DB;

/**
 * Reparenting and the two operations it must never be done without: knowing
 * what it moves, and knowing it cannot form a cycle.
 */
final class TerritoryAdministrator
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly RedirectRegistrar $redirects,
    ) {}

    /**
     * The count an administrator must see before a reparent proceeds —
     * computed over the whole subtree, not the immediate children, because
     * the requirement is to warn about everything the move actually
     * displaces.
     *
     * @return array{descendants: int, objects: int}
     */
    public function blastRadius(Territory $territory): array
    {
        $descendantIds = $territory->descendants()->pluck('id');

        return [
            'descendants' => $descendantIds->count(),
            'objects' => DB::table('objects')
                ->whereIn('territory_id', [$territory->id, ...$descendantIds])
                ->whereNull('deleted_at')
                ->count(),
        ];
    }

    /**
     * Moves $territory under $newParentId (or to a root, when null), then
     * repairs what a raw foreign-key flip would leave stale across the whole
     * subtree: the denormalized `country_id` and the cached full slug path
     * — both one transaction, since a partial rewrite would leave some
     * descendants pointing at the old country.
     *
     * @throws TerritoryCycleException when $newParentId is $territory itself or one of its own descendants
     */
    public function reparent(Territory $territory, ?int $newParentId, User $actor): void
    {
        if ($newParentId !== null) {
            $this->assertNoCycle($territory, $newParentId);
        }

        $radius = $this->blastRadius($territory);

        DB::transaction(function () use ($territory, $newParentId, $actor, $radius): void {
            $originalParentId = $territory->parent_id;

            $territory->forceFill(['parent_id' => $newParentId])->save();

            $newCountryId = $newParentId !== null
                ? Territory::query()->findOrFail($newParentId)->country_id
                : $territory->country_id;

            /** @var Territory $refreshed */
            $refreshed = $territory->fresh();

            $subtreeIds = [$territory->id, ...$refreshed->descendants()->pluck('id')];

            Territory::query()->whereIn('id', $subtreeIds)->update(['country_id' => $newCountryId]);

            /** @var Territory $reloaded */
            $reloaded = $territory->fresh();

            $this->recomputeSlugPaths($reloaded);

            $this->journal->record(
                event: 'territory_reparented',
                target: $territory,
                oldValues: ['parent_id' => $originalParentId],
                newValues: ['parent_id' => $newParentId, 'affected_descendants' => $radius['descendants'], 'affected_objects' => $radius['objects']],
                actor: $actor,
                tags: ['geography'],
            );
        });
    }

    /**
     * Recomputes the cached full slug path for $territory and every
     * descendant, in every language either carries a translation for. Called
     * after a reparent, and must also be called after any slug edit — an
     * ancestor's own slug change invalidates every descendant's cached path
     * just as surely as a reparent does. Registers a redirect from each
     * translation's own previous `(country, path)` to its new one, in the
     * same pass — the address a visitor or search engine already has must
     * keep resolving. The whole recomputation is one transaction: a refused
     * reuse partway through a large subtree must leave every already-written
     * node in this call rolled back, not half-migrated.
     *
     * @throws SlugReuseRefusedException when a computed path is already claimed by another node's active redirect
     */
    public function recomputeSlugPaths(Territory $territory): void
    {
        DB::transaction(function () use ($territory): void {
            /** @var array<int, string> $countryCodesById */
            $countryCodesById = Country::query()->pluck('code', 'id')->all();

            $nodes = $territory->descendantsAndSelf()->with('translations')->get();

            /** @var Territory $node */
            foreach ($nodes as $node) {
                $ancestorChain = $node->ancestorsAndSelf()->with('translations')->orderBy('depth')->get();

                /** @var TerritoryTranslation $translation */
                foreach ($node->translations as $translation) {
                    $segments = [];

                    /** @var Territory $ancestor */
                    foreach ($ancestorChain as $ancestor) {
                        /** @var ?TerritoryTranslation $ancestorTranslation */
                        $ancestorTranslation = $ancestor->id === $node->id
                            ? $translation
                            : $ancestor->translations->firstWhere('locale', $translation->locale);

                        $segments[] = $ancestorTranslation->slug ?? null;
                    }

                    if (in_array(null, $segments, true)) {
                        continue;
                    }

                    $previousCountryCode = isset($countryCodesById[$translation->country_id])
                        ? mb_strtolower($countryCodesById[$translation->country_id])
                        : null;
                    $previousPath = $translation->full_slug_path;
                    $oldPath = $previousCountryCode !== null && $previousPath !== null
                        ? "{$previousCountryCode}/{$previousPath}"
                        : null;

                    $newCountryCode = mb_strtolower($countryCodesById[$node->country_id] ?? '');
                    $newFullSlugPath = implode('/', $segments);
                    $newPath = "{$newCountryCode}/{$newFullSlugPath}";

                    if ($newPath !== $oldPath && $this->redirects->isClaimed($translation->locale, $newPath)) {
                        throw SlugReuseRefusedException::forPath($translation->locale, $newPath);
                    }

                    // Denormalized alongside full_slug_path — Postgres cannot
                    // scope a unique index through a foreign key's own parent
                    // table, and the resolver's uniqueness boundary is the
                    // path within a country, not the path alone.
                    $translation->country_id = $node->country_id;
                    $translation->full_slug_path = $newFullSlugPath;
                    $translation->save();

                    $this->redirects->registerPathChange($translation->locale, $oldPath, $newPath);
                }
            }
        });
    }

    private function assertNoCycle(Territory $territory, int $newParentId): void
    {
        if ($newParentId === $territory->id) {
            throw TerritoryCycleException::forMove($territory->id, $newParentId);
        }

        $isDescendant = $territory->descendants()->where('id', $newParentId)->exists();

        if ($isDescendant) {
            throw TerritoryCycleException::forMove($territory->id, $newParentId);
        }
    }
}
