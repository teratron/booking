<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use App\Models\Object_;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Flags existing objects that plausibly represent the same real-world place
 * as an incoming import row, on five independent signals — name, phone,
 * website, address, and coordinate proximity — each surfaced by name rather
 * than folded into one opaque "possible duplicate" flag.
 *
 * This service never writes anything and never decides anything: it is a
 * read-only comparison against the existing catalog, called from the import
 * pipeline's own preview pass. A flagged candidate is presented to an
 * operator; merging two records into one is always a separate,
 * administrator-confirmed action.
 */
final class DuplicateDetectionService
{
    /**
     * pg_trgm's own default operator threshold
     * (`pg_trgm.similarity_threshold`), reused here rather than a stricter
     * figure invented for this service: it already tolerates a reordered
     * short phrase ("Hotel Astoria" vs "Astoria Hotel") while staying well
     * short of matching two names that merely share a common word.
     */
    private const float NAME_SIMILARITY_THRESHOLD = 0.3;

    /**
     * Addresses carry as much incidental spelling and abbreviation variation
     * as names do ("12 Main St" vs "12 Main Street"), so the floor matches
     * the name threshold rather than being stricter.
     */
    private const float ADDRESS_SIMILARITY_THRESHOLD = 0.3;

    /**
     * A real distance in metres, not a coordinate-delta approximation —
     * tight enough that two genuinely distinct objects in the same
     * settlement (a hotel and the restaurant three streets over) do not
     * collide on this signal alone, and wide enough to catch the GPS pin
     * drift a phone or a re-geocoded address commonly introduces.
     */
    private const int COORDINATE_RADIUS_METERS = 100;

    /** Caps how many existing objects one signal reports, so one very
     * generic name or a shared building address cannot flood the preview
     * with candidates no operator would usefully review. */
    private const int MAX_CANDIDATES_PER_SIGNAL = 5;

    /**
     * @param  int|null  $excludeObjectId  an object the row is already known
     *                                     to resolve to (an update, matched
     *                                     by ulid or id) — excluded so a row
     *                                     is never flagged as a duplicate of
     *                                     the very record it would update
     * @return list<DuplicateCandidate>
     */
    public function detect(NewObjectSignals $signals, ?int $excludeObjectId = null): array
    {
        if (! $signals->hasAnySignal()) {
            return [];
        }

        /** @var array<int, list<DuplicateSignal>> $matches */
        $matches = [];

        $this->collectNameMatches($signals->name, $excludeObjectId, $matches);
        $this->collectContactMatches($signals->phone, 'phone', $excludeObjectId, $matches);
        $this->collectContactMatches($signals->website, 'website', $excludeObjectId, $matches);
        $this->collectAddressMatches($signals->address, $excludeObjectId, $matches);
        $this->collectCoordinateMatches($signals->latitude, $signals->longitude, $excludeObjectId, $matches);

        if ($matches === []) {
            return [];
        }

        $labels = $this->resolveLabels(array_keys($matches));

        $candidates = [];

        foreach ($matches as $objectId => $signalList) {
            $candidates[] = new DuplicateCandidate($objectId, $labels[$objectId] ?? "#{$objectId}", $signalList);
        }

        return $candidates;
    }

    /** @param  array<int, list<DuplicateSignal>>  $matches */
    private function collectNameMatches(?string $name, ?int $excludeObjectId, array &$matches): void
    {
        $name = trim((string) $name);

        if ($name === '') {
            return;
        }

        $rows = DB::table('object_translations')
            ->join('objects', 'objects.id', '=', 'object_translations.object_id')
            ->whereNull('objects.deleted_at')
            ->when($excludeObjectId !== null, fn ($query) => $query->where('objects.id', '!=', $excludeObjectId))
            ->whereRaw('similarity(object_translations.name, ?) >= ?', [$name, self::NAME_SIMILARITY_THRESHOLD])
            ->selectRaw('objects.id as object_id, similarity(object_translations.name, ?) as score', [$name])
            ->orderByDesc('score')
            ->limit(self::MAX_CANDIDATES_PER_SIGNAL)
            ->get();

        foreach ($rows as $row) {
            $matches[(int) $row->object_id][] = new DuplicateSignal(
                'name',
                __('panel.data_transfer.import.duplicates.signals.name', ['score' => number_format((float) $row->score, 2)]),
            );
        }
    }

    /**
     * Phone and website live on `contact_channels`, scoped by the channel's
     * own type key — never a column on `objects` itself, which carries no
     * contact information at all. Both are matched after normalization
     * rather than by raw equality: a phone number formatted with spaces or a
     * leading "+" and one stripped to bare digits are the same number, and
     * "https://www.example.com/" and "example.com" are the same site.
     *
     * @param  array<int, list<DuplicateSignal>>  $matches
     */
    private function collectContactMatches(?string $value, string $channelKey, ?int $excludeObjectId, array &$matches): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $normalized = $channelKey === 'phone' ? self::normalizePhone($value) : self::normalizeWebsite($value);

        if ($normalized === '') {
            return;
        }

        $normalizedColumn = $channelKey === 'phone'
            ? "regexp_replace(contact_channels.raw_value, '[^0-9]', '', 'g')"
            : "rtrim(regexp_replace(lower(contact_channels.raw_value), '^https?://(www\\.)?', ''), '/')";

        $rows = DB::table('contact_channels')
            ->join('contact_channel_types', 'contact_channel_types.id', '=', 'contact_channels.contact_channel_type_id')
            ->join('objects', 'objects.id', '=', 'contact_channels.object_id')
            ->where('contact_channel_types.key', $channelKey)
            ->where('contact_channels.is_active', true)
            ->whereNull('objects.deleted_at')
            ->when($excludeObjectId !== null, fn ($query) => $query->where('objects.id', '!=', $excludeObjectId))
            ->whereRaw("{$normalizedColumn} = ?", [$normalized])
            ->select('objects.id as object_id')
            ->limit(self::MAX_CANDIDATES_PER_SIGNAL)
            ->get();

        foreach ($rows as $row) {
            $matches[(int) $row->object_id][] = new DuplicateSignal(
                $channelKey,
                __("panel.data_transfer.import.duplicates.signals.{$channelKey}"),
            );
        }
    }

    /** @param  array<int, list<DuplicateSignal>>  $matches */
    private function collectAddressMatches(?string $address, ?int $excludeObjectId, array &$matches): void
    {
        $address = trim((string) $address);

        if ($address === '') {
            return;
        }

        $rows = DB::table('objects')
            ->whereNull('deleted_at')
            ->whereNotNull('address')
            ->when($excludeObjectId !== null, fn ($query) => $query->where('id', '!=', $excludeObjectId))
            ->whereRaw('similarity(address, ?) >= ?', [$address, self::ADDRESS_SIMILARITY_THRESHOLD])
            ->selectRaw('id as object_id, similarity(address, ?) as score', [$address])
            ->orderByDesc('score')
            ->limit(self::MAX_CANDIDATES_PER_SIGNAL)
            ->get();

        foreach ($rows as $row) {
            $matches[(int) $row->object_id][] = new DuplicateSignal(
                'address',
                __('panel.data_transfer.import.duplicates.signals.address', ['score' => number_format((float) $row->score, 2)]),
            );
        }
    }

    /**
     * `objects.geom` carries the GiST index built for the catalog's own
     * spatial queries, but nothing in this codebase's own object-creation
     * paths (the admin form, the cabinet form, or the objects importer)
     * populates it — only `latitude`/`longitude` are ever written, `geom`
     * stays null unless a row was seeded directly with raw SQL. Comparing
     * against `geom` alone would make this signal silently never fire
     * against the overwhelming majority of real objects, so the geography
     * point is built from `latitude`/`longitude` whenever `geom` itself is
     * absent — still a real `ST_DWithin` distance, never a bounding-box
     * approximation, just computed from whichever of the two columns an
     * object actually carries.
     *
     * @param  array<int, list<DuplicateSignal>>  $matches
     */
    private function collectCoordinateMatches(?float $latitude, ?float $longitude, ?int $excludeObjectId, array &$matches): void
    {
        if ($latitude === null || $longitude === null) {
            return;
        }

        $point = 'COALESCE(geom, ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)::geography)';

        $rows = DB::table('objects')
            ->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query->whereNotNull('geom')
                    ->orWhere(function (Builder $nested): void {
                        $nested->whereNotNull('latitude')->whereNotNull('longitude');
                    });
            })
            ->when($excludeObjectId !== null, fn ($query) => $query->where('id', '!=', $excludeObjectId))
            ->whereRaw(
                "ST_DWithin({$point}, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                [$longitude, $latitude, self::COORDINATE_RADIUS_METERS],
            )
            ->selectRaw(
                "id as object_id, ST_Distance({$point}, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance_meters",
                [$longitude, $latitude],
            )
            ->orderBy('distance_meters')
            ->limit(self::MAX_CANDIDATES_PER_SIGNAL)
            ->get();

        foreach ($rows as $row) {
            $matches[(int) $row->object_id][] = new DuplicateSignal(
                'coordinates',
                __('panel.data_transfer.import.duplicates.signals.coordinates', ['meters' => (int) round((float) $row->distance_meters)]),
            );
        }
    }

    /**
     * @param  list<int>  $objectIds
     * @return array<int, string>
     */
    private function resolveLabels(array $objectIds): array
    {
        // withUnmoderated(): a candidate awaiting its first approval is
        // still a real duplicate worth flagging. withTrashed() is not
        // applied — a soft-deleted object is already excluded from every
        // signal query above and has no business surfacing as a candidate.
        return Object_::query()
            ->withUnmoderated()
            ->whereIn('id', $objectIds)
            ->get()
            ->mapWithKeys(static fn (Object_ $object): array => [$object->id => $object->name ?? "#{$object->id}"])
            ->all();
    }

    private static function normalizePhone(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    private static function normalizeWebsite(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('#^https?://(www\.)?#', '', $value);

        return rtrim($value, '/');
    }
}
