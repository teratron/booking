<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Server-side search for the three large, unbounded registries (objects,
 * territories, staff/owner users) that a naive `Select::make()->options(fn
 * () => Model::query()->get()->mapWithKeys(...))` would otherwise hydrate in
 * full on every form render.
 *
 * At production volume that pattern loaded all 52,800 objects with their
 * 105,600 translations, or all 6,270 territories, into Eloquent models for
 * a single dropdown — 55+ seconds and 7+ MB of HTML per screen, exhausting a
 * 512 MB memory limit outright. `FinancialRecordForm` and `EditObject`'s own
 * merge action already used the correct `getSearchResultsUsing()` +
 * `getOptionLabelUsing()` pair before this class existed; it is extracted
 * here because the same pair needs to be applied consistently everywhere
 * else a form links to one of these three tables, not reinvented per call
 * site.
 */
final class SearchableModelSelect
{
    private const int RESULT_LIMIT = 50;

    /**
     * An object select, searched by translated name. Deliberately reaches
     * unmoderated and soft-deleted-excluded objects (`withUnmoderated()`,
     * the same scope `EditObject`'s merge action and the importer use) —
     * an administrator linking a news item or promotion to an object needs
     * to find it regardless of its current moderation state.
     */
    public static function objects(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Object_::query()
                ->withUnmoderated()
                ->whereHas('translations', fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->mapWithKeys(fn (Object_ $object): array => [$object->id => self::objectLabel($object)])
                ->all())
            ->getOptionLabelUsing(function (int|string|null $value): ?string {
                $object = Object_::query()->withUnmoderated()->find($value);

                return $object instanceof Object_ ? self::objectLabel($object) : null;
            });
    }

    /**
     * A territory select, searched by translated name.
     *
     * @param  int|null  $excludeId  omitted from results — a reparenting
     *                               form's own territory, so it can never
     *                               become its own parent.
     */
    public static function territories(string $name, string $label, ?int $excludeId = null): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Territory::query()
                ->where('is_active', true)
                ->when($excludeId !== null, fn (Builder $query) => $query->whereKeyNot($excludeId))
                ->whereHas('translations', fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
                ->all())
            ->getOptionLabelUsing(function (int|string|null $value): ?string {
                $territory = Territory::query()->find($value);

                return $territory instanceof Territory ? ($territory->name ?? "#{$territory->id}") : null;
            });
    }

    /** A user select, searched by name. */
    public static function users(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => User::query()
                ->where('name', 'ilike', "%{$search}%")
                ->limit(self::RESULT_LIMIT)
                ->pluck('name', 'id')
                ->all())
            ->getOptionLabelUsing(fn (int|string|null $value): ?string => User::query()->find($value)?->name);
    }

    private static function objectLabel(Object_ $object): string
    {
        $object->loadMissing('translations');

        return ($object->name ?? "#{$object->id}")." (#{$object->id})";
    }
}
