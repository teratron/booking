<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds catalog-ranking and territory-subtree-expansion benchmark fixtures
 * at realistic volume — a dozen fixtures measure nothing, since both hot
 * paths behave differently once the table actually holds tens of thousands
 * of rows. Deliberately separate from the registry seeders so `migrate:fresh
 * --seed` stays fast for the normal development loop; run explicitly via
 * `db:seed --class=DemoVolumeSeeder`.
 *
 * Every insert is chunked and IDs are assigned in PHP rather than left to
 * `insertGetId()` per row — the difference between a handful of bulk
 * `INSERT`s and hundreds of thousands of round trips. Sequences are fast-
 * forwarded past the manually-assigned range at the end so the application's
 * own future inserts never collide with a demo row's ID.
 */
final class DemoVolumeSeeder extends Seeder
{
    private const int CHUNK_SIZE = 2000;

    private const int LEVEL1_PER_COUNTRY = 10;

    private const int LEVEL2_PER_LEVEL1 = 8;

    private const int LEVEL3_PER_LEVEL2 = 5;

    private const int LEVEL4_PER_LEVEL3 = 4;

    private const int OWNERS_COUNT = 3000;

    private const int OBJECTS_PER_LEAF = 11;

    /** @var array<string, array{lat: array{float, float}, lng: array{float, float}}> */
    private const array COUNTRY_BOUNDS = [
        'MD' => ['lat' => [45.5, 48.5], 'lng' => [26.5, 30.2]],
        'UA' => ['lat' => [44.5, 52.3], 'lng' => [22.0, 40.2]],
        'GE' => ['lat' => [41.0, 43.6], 'lng' => [40.0, 46.7]],
    ];

    public function run(): void
    {
        $languages = DB::table('languages')->where('is_active', true)->pluck('code')->all();
        $objectTypeIds = DB::table('object_types')->pluck('id')->all();

        $leafTerritoryIds = $this->seedTerritories($languages);
        $ownerIds = $this->seedOwners();
        $this->seedObjects($leafTerritoryIds, $ownerIds, $objectTypeIds, $languages);
    }

    /**
     * Builds a fixed-depth territory tree per country (10 → 80 → 400 →
     * 1,600 nodes) and returns the leaf (level-4) IDs objects attach to.
     * Depth counts are deterministic, not random, so the total node count
     * — and the margin over the 3,000-node floor — is predictable.
     *
     * @param  array<int, string>  $languages
     * @return array<int, int>
     */
    private function seedTerritories(array $languages): array
    {
        $countries = DB::table('countries')->get(['id', 'code']);
        $levelIdsByCountry = DB::table('territory_levels')
            ->orderBy('depth_rank')
            ->get(['id', 'country_id', 'depth_rank'])
            ->groupBy('country_id');

        $nextId = (int) (DB::table('territories')->max('id') ?? 0) + 1;
        $leafIds = [];
        $territoryBuffer = [];
        $translationBuffer = [];

        foreach ($countries as $country) {
            $levels = ($levelIdsByCountry[$country->id] ?? collect())->pluck('id', 'depth_rank');
            $bounds = self::COUNTRY_BOUNDS[$country->code];
            // Reset per country, not accumulated across the whole run: no
            // country's tree ever references another country's territory as
            // a parent, and holding every country's paths in memory at once
            // is exactly the kind of growth this seeder's own chunking
            // discipline exists to avoid.
            $fullPathById = [];
            // The slug this seeder assigns (`territory-{$id}`) is language-
            // invariant, so one path-by-id map serves every locale — no need
            // to key it per language the way a real per-language slug would.

            $level1Ids = [];
            for ($i = 1; $i <= self::LEVEL1_PER_COUNTRY; $i++) {
                $id = $nextId++;
                $level1Ids[] = $id;
                $this->appendTerritory($territoryBuffer, $translationBuffer, $fullPathById, $id, null, $country->id, $levels[1], $bounds, "L1-{$i}", $languages);
            }

            $level2Ids = [];
            foreach ($level1Ids as $parentId) {
                for ($i = 1; $i <= self::LEVEL2_PER_LEVEL1; $i++) {
                    $id = $nextId++;
                    $level2Ids[] = $id;
                    $this->appendTerritory($territoryBuffer, $translationBuffer, $fullPathById, $id, $parentId, $country->id, $levels[2], $bounds, "L2-{$id}", $languages);
                }
            }

            $level3Ids = [];
            foreach ($level2Ids as $parentId) {
                for ($i = 1; $i <= self::LEVEL3_PER_LEVEL2; $i++) {
                    $id = $nextId++;
                    $level3Ids[] = $id;
                    $this->appendTerritory($territoryBuffer, $translationBuffer, $fullPathById, $id, $parentId, $country->id, $levels[3], $bounds, "L3-{$id}", $languages);
                }
            }

            foreach ($level3Ids as $parentId) {
                for ($i = 1; $i <= self::LEVEL4_PER_LEVEL3; $i++) {
                    $id = $nextId++;
                    $leafIds[] = $id;
                    $this->appendTerritory($territoryBuffer, $translationBuffer, $fullPathById, $id, $parentId, $country->id, $levels[4], $bounds, "L4-{$id}", $languages);
                }
            }

            // Forced at every country boundary, territories before their
            // translations — the same ordering hazard as seedObjects()
            // applies here, so this does not rely on either buffer crossing
            // CHUNK_SIZE on its own to stay correct.
            $this->flush('territories', $territoryBuffer, force: true);
            $this->flush('territory_translations', $translationBuffer, force: true);
        }

        DB::statement("select setval(pg_get_serial_sequence('territories', 'id'), (select max(id) from territories))");

        return $leafIds;
    }

    /**
     * @param  array<int, array<string, mixed>>  $territoryBuffer
     * @param  array<int, array<string, mixed>>  $translationBuffer
     * @param  array<int, string>  $fullPathById  keyed by territory id, populated as each node is appended
     * @param  array{lat: array{float, float}, lng: array{float, float}}  $bounds
     * @param  array<int, string>  $languages
     */
    private function appendTerritory(
        array &$territoryBuffer,
        array &$translationBuffer,
        array &$fullPathById,
        int $id,
        ?int $parentId,
        int $countryId,
        int $levelId,
        array $bounds,
        string $label,
        array $languages,
    ): void {
        $lat = $this->randomBetween($bounds['lat'][0], $bounds['lat'][1]);
        $lng = $this->randomBetween($bounds['lng'][0], $bounds['lng'][1]);

        $territoryBuffer[] = [
            'id' => $id,
            'parent_id' => $parentId,
            'country_id' => $countryId,
            'level_id' => $levelId,
            'latitude' => $lat,
            'longitude' => $lng,
            // Larastan wants a literal-string here; $lat/$lng are floats
            // this method formats itself, never user input, so the raw
            // expression carries no injection risk.
            // @phpstan-ignore argument.type
            'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $slug = "territory-{$id}";
        $fullPath = $parentId !== null ? "{$fullPathById[$parentId]}/{$slug}" : $slug;
        $fullPathById[$id] = $fullPath;

        foreach ($languages as $locale) {
            $translationBuffer[] = [
                'territory_id' => $id,
                'country_id' => $countryId,
                'locale' => $locale,
                'name' => "{$label} ({$locale})",
                'slug' => $slug,
                'full_slug_path' => $fullPath,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    /** @return array<int, int> */
    private function seedOwners(): array
    {
        // Hashed once and reused — bcrypt is deliberately slow, and hashing
        // it per row would turn owner seeding into the slowest part of this
        // seeder for a password no demo login ever needs to be unique.
        $hashedPassword = bcrypt('demo-password');
        $nextId = (int) (DB::table('users')->max('id') ?? 0) + 1;
        $buffer = [];
        $ownerIds = [];

        for ($i = 0; $i < self::OWNERS_COUNT; $i++) {
            $id = $nextId++;
            $ownerIds[] = $id;

            $buffer[] = [
                'id' => $id,
                'name' => "Demo Owner {$id}",
                'email' => "demo-owner-{$id}@example.test",
                'password' => $hashedPassword,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->flush('users', $buffer);
        }

        $this->flush('users', $buffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('users', 'id'), (select max(id) from users))");

        return $ownerIds;
    }

    /**
     * @param  array<int, int>  $leafTerritoryIds
     * @param  array<int, int>  $ownerIds
     * @param  array<int, int>  $objectTypeIds
     * @param  array<int, string>  $languages
     */
    private function seedObjects(array $leafTerritoryIds, array $ownerIds, array $objectTypeIds, array $languages): void
    {
        $territories = DB::table('territories')
            ->whereIn('id', $leafTerritoryIds)
            ->get(['id', 'country_id', 'latitude', 'longitude'])
            ->keyBy('id');

        $nextId = (int) (DB::table('objects')->max('id') ?? 0) + 1;
        $objectBuffer = [];
        $translationBuffer = [];
        $ownerCount = count($ownerIds);

        foreach ($leafTerritoryIds as $territoryId) {
            $territory = $territories[$territoryId]
                ?? throw new RuntimeException("Territory {$territoryId} not found during demo seeding.");

            for ($i = 0; $i < self::OBJECTS_PER_LEAF; $i++) {
                $id = $nextId++;
                $objectTypeId = $objectTypeIds[array_rand($objectTypeIds)];
                $ownerId = $ownerIds[$id % $ownerCount];

                // Jittered a fraction of a degree around the leaf
                // territory's own point — near enough to be a realistic
                // object-within-territory placement, not a repeat of the
                // exact same coordinate for every object in the same node.
                $lat = round((float) $territory->latitude + $this->randomBetween(-0.02, 0.02), 7);
                $lng = round((float) $territory->longitude + $this->randomBetween(-0.02, 0.02), 7);

                $objectBuffer[] = [
                    'id' => $id,
                    'ulid' => (string) Str::ulid(),
                    'owner_id' => $ownerId,
                    'object_type_id' => $objectTypeId,
                    'territory_id' => $territoryId,
                    'country_id' => $territory->country_id,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    // Larastan wants a literal-string here; $lat/$lng are
                    // floats this method formats itself, never user input,
                    // so the raw expression carries no injection risk.
                    // @phpstan-ignore argument.type
                    'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
                    'status' => 'published',
                    'moderation_status' => 'approved',
                    'availability_status' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                foreach ($languages as $locale) {
                    $translationBuffer[] = [
                        'object_id' => $id,
                        'locale' => $locale,
                        'name' => "Demo Object {$id} ({$locale})",
                        'slug' => "demo-object-{$id}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Flushed together, gated on the objects buffer alone: each
                // object produces one row here but count($languages) rows in
                // the translation buffer, so letting each buffer trigger its
                // own flush independently lets translations for objects
                // still sitting in the unflushed object buffer reach the
                // database first — a foreign key violation, since the object
                // they reference does not exist yet. Objects are always
                // inserted before the translations that reference them.
                if (count($objectBuffer) >= self::CHUNK_SIZE) {
                    $this->flush('objects', $objectBuffer, force: true);
                    $this->flush('object_translations', $translationBuffer, force: true);
                }
            }
        }

        $this->flush('objects', $objectBuffer, force: true);
        $this->flush('object_translations', $translationBuffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('objects', 'id'), (select max(id) from objects))");
    }

    /**
     * Inserts and clears the buffer once it reaches the chunk size — the
     * mechanism that keeps memory bounded regardless of total row count.
     *
     * @param  array<int, array<string, mixed>>  $buffer
     */
    private function flush(string $table, array &$buffer, bool $force = false): void
    {
        if (! $force && count($buffer) < self::CHUNK_SIZE) {
            return;
        }

        if ($buffer === []) {
            return;
        }

        DB::table($table)->insert($buffer);
        $buffer = [];
    }

    private function randomBetween(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 7);
    }
}
