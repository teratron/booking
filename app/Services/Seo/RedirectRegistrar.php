<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Redirect;

/**
 * The single write path for the `redirects` table. Every slug-change call
 * site — territory edits, territory reparenting, object slug edits, moderated
 * or not — calls {@see self::registerPathChange()} in the same operation as
 * the write that changed the address, never after. A redirect added later is
 * added after the traffic to the old path has already been lost.
 */
final class RedirectRegistrar
{
    /**
     * Records that `$oldPath` now redirects to `$newPath` in `$locale`.
     * A no-op when the path did not actually change, when either side is
     * empty, or when a path would redirect to itself. Collapses a chain
     * rather than lengthening it: any existing redirect whose own target was
     * `$oldPath` is repointed straight to `$newPath`, so a visitor following
     * an old link never bounces through more than one hop.
     */
    public function registerPathChange(string $locale, ?string $oldPath, ?string $newPath): void
    {
        if ($oldPath === null || $newPath === null || $oldPath === $newPath) {
            return;
        }

        Redirect::query()
            ->where('locale', $locale)
            ->where('to_path', $oldPath)
            ->update(['to_path' => $newPath]);

        Redirect::query()->updateOrCreate(
            ['locale' => $locale, 'from_path' => $oldPath],
            ['to_path' => $newPath, 'is_active' => true],
        );
    }

    /**
     * Whether `$path` is already claimed as the origin of an active redirect
     * in `$locale` — the guard a slug-assigning form consults before saving,
     * so a slug is never reused while a redirect still promises it leads
     * somewhere else.
     */
    public function isClaimed(string $locale, string $path): bool
    {
        return Redirect::query()
            ->where('locale', $locale)
            ->where('from_path', $path)
            ->where('is_active', true)
            ->exists();
    }
}
