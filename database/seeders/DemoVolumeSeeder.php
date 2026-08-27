<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContactChannelType;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Audit\AuditJournal;
use App\Services\Contact\ContactChannelLinkResolver;
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
 * Beyond the object/territory volume story, this also populates every other
 * table a fresh install otherwise leaves at zero rows — contact channels,
 * banners, editorial content (articles, news, promotions), reviews, and (via
 * the Eloquent model layer specifically, see {@see self::seedAuditTrail()})
 * the action journal — so the admin panel, the owner cabinet, and the public
 * site all have real rows to render against, not just the catalog itself.
 *
 * Every insert is chunked and IDs are assigned in PHP rather than left to
 * `insertGetId()` per row — the difference between a handful of bulk
 * `INSERT`s and hundreds of thousands of round trips. Sequences are fast-
 * forwarded past the manually-assigned range at the end so the application's
 * own future inserts never collide with a demo row's ID. A table nothing
 * else in this seeder references by ID (every `*_translations` table, the
 * article pivots, contact channels, reviews) skips manual ID assignment
 * entirely and lets Postgres's own sequence assign it during the bulk
 * insert — simpler, and exactly how this class already treated
 * `territory_translations`/`object_translations` before this change.
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

    private const int BANNERS_PER_SLOT = 4;

    private const int ARTICLES_COUNT = 40;

    private const int NEWS_ITEMS_COUNT = 60;

    private const int PROMOTIONS_COUNT = 30;

    private const int REVIEWED_OBJECTS_COUNT = 1500;

    private const int AUDIT_SAMPLE_COUNT = 30;

    /**
     * A curated bilingual registry, not volume — the same reason
     * `ObjectTypeSeeder`/`ContactChannelTypeSeeder` hardcode real copy
     * instead of the generic "Demo X (locale)" placeholder text the
     * bulk-content methods below use for articles, news, and promotions.
     *
     * @var array<int, array{slug: string, name: array<string, string>}>
     */
    private const array ARTICLE_CATEGORIES = [
        ['slug' => 'travel-tips', 'name' => ['en' => 'Travel Tips', 'ru' => 'Советы путешественникам']],
        ['slug' => 'destinations', 'name' => ['en' => 'Destinations', 'ru' => 'Направления']],
        ['slug' => 'food-and-dining', 'name' => ['en' => 'Food & Dining', 'ru' => 'Еда и рестораны']],
        ['slug' => 'events', 'name' => ['en' => 'Events', 'ru' => 'События']],
        ['slug' => 'guides', 'name' => ['en' => 'Guides', 'ru' => 'Гиды']],
        ['slug' => 'culture', 'name' => ['en' => 'Culture', 'ru' => 'Культура']],
    ];

    /**
     * `article_tags` carries no translations sibling table (see the
     * migration's own note), so a plain, largely language-neutral English
     * label is the whole story here.
     *
     * @var list<string>
     */
    private const array ARTICLE_TAGS = [
        'Beach', 'Mountains', 'Family', 'Budget', 'Luxury',
        'Adventure', 'Wine', 'History', 'Nightlife', 'Wellness',
    ];

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
        $bannerSlotIds = DB::table('banner_slots')->pluck('id')->all();
        /** @var array<int, ContactChannelType> $contactChannelTypes */
        $contactChannelTypes = ContactChannelType::query()->orderBy('display_order')->get()->all();

        $leafTerritoryIds = $this->seedTerritories($languages);
        $ownerIds = $this->seedOwners();
        [$firstObjectId, $lastObjectId] = $this->seedObjects($leafTerritoryIds, $ownerIds, $objectTypeIds, $languages);

        $this->seedContactChannels($firstObjectId, $lastObjectId, $contactChannelTypes);
        $this->seedBanners($languages, $bannerSlotIds, $leafTerritoryIds, $objectTypeIds);

        $articleCategoryIds = $this->seedArticleCategories($languages);
        $articleTagIds = $this->seedArticleTags();
        $this->seedArticles($languages, $ownerIds, $articleCategoryIds, $articleTagIds, $leafTerritoryIds, $firstObjectId, $lastObjectId);
        $this->seedNewsItems($languages, $ownerIds, $articleCategoryIds, $leafTerritoryIds, $firstObjectId, $lastObjectId);
        $this->seedPromotions($languages, $firstObjectId, $lastObjectId);
        $this->seedReviews($ownerIds, $firstObjectId, $lastObjectId);
        $this->seedAuditTrail($firstObjectId, $lastObjectId);
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
     * @return array{0: int, 1: int} the first and last object ID this run assigned, in that order
     */
    private function seedObjects(array $leafTerritoryIds, array $ownerIds, array $objectTypeIds, array $languages): array
    {
        $territories = DB::table('territories')
            ->whereIn('id', $leafTerritoryIds)
            ->get(['id', 'country_id', 'latitude', 'longitude'])
            ->keyBy('id');

        $nextId = (int) (DB::table('objects')->max('id') ?? 0) + 1;
        $firstObjectId = $nextId;
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

        return [$firstObjectId, $nextId - 1];
    }

    /**
     * Attaches 1-3 contact channels to a representative majority of the
     * objects just seeded — mixed channel types, and a deliberate one-in-
     * five left `is_active = false` so the inactive-channel rendering
     * branch has real rows too. Deterministic on the object ID rather than
     * randomised: the counts this produces stay identical on every run,
     * which is what the volume test asserts against.
     *
     * @param  array<int, ContactChannelType>  $channelTypes
     */
    private function seedContactChannels(int $firstObjectId, int $lastObjectId, array $channelTypes): void
    {
        $resolver = new ContactChannelLinkResolver;
        $typeCount = count($channelTypes);
        $buffer = [];

        for ($objectId = $firstObjectId; $objectId <= $lastObjectId; $objectId++) {
            // One object in five gets no contact channel at all, so a query
            // filtering for "objects with no contact channel" still has
            // real rows to find — the other four out of five are the
            // "representative majority" the task calls for.
            if ($objectId % 5 === 0) {
                continue;
            }

            $channelCount = 1 + ($objectId % 3);

            for ($i = 0; $i < $channelCount; $i++) {
                $type = $channelTypes[($objectId + $i) % $typeCount];
                $rawValue = $this->contactChannelRawValue($type->key, $objectId, $i);

                $buffer[] = [
                    'object_id' => $objectId,
                    'contact_channel_type_id' => $type->id,
                    'raw_value' => $rawValue,
                    'derived_link' => $resolver->resolve($type, $rawValue),
                    'label' => null,
                    'display_order' => $i,
                    // One in five left inactive, to exercise that branch too.
                    'is_active' => ($objectId + $i) % 5 !== 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $this->flush('contact_channels', $buffer);
            }
        }

        $this->flush('contact_channels', $buffer, force: true);
    }

    /**
     * A demo raw value shaped like the channel it belongs to — a phone-style
     * string for phone/WhatsApp/Viber, a handle for the messenger/social
     * channels, an address for email, a URL for the website channel — so
     * `ContactChannelLinkResolver` produces a realistic `derived_link`
     * rather than a template applied to nonsense input.
     */
    private function contactChannelRawValue(string $key, int $objectId, int $index): string
    {
        return match ($key) {
            'phone', 'whatsapp', 'viber' => sprintf('+373 69%05d', ($objectId + $index) % 100000),
            'telegram' => "demo_object_{$objectId}",
            'email' => "owner{$objectId}@example.test",
            'website' => "https://demo-object-{$objectId}.example.test",
            'instagram', 'facebook' => "demo.object.{$objectId}",
            default => "demo-value-{$objectId}-{$index}",
        };
    }

    /**
     * Seeds a modest banner inventory — enough for the back-office banner
     * list and reporting screens to have real rows, not the volume story
     * the rest of this seeder exists for. Spread across every seeded slot,
     * with all three targeting shapes represented (untargeted, territory,
     * category) and a spread of flight windows (live, elapsed, scheduled,
     * deactivated) so the admin list shows every state a real inventory
     * eventually accumulates.
     *
     * @param  array<int, string>  $languages
     * @param  array<int, int>  $bannerSlotIds
     * @param  array<int, int>  $leafTerritoryIds
     * @param  array<int, int>  $objectTypeIds
     */
    private function seedBanners(array $languages, array $bannerSlotIds, array $leafTerritoryIds, array $objectTypeIds): void
    {
        $nextId = (int) (DB::table('banners')->max('id') ?? 0) + 1;
        $bannerBuffer = [];
        $translationBuffer = [];
        $targetBuffer = [];
        $sequence = 0;

        foreach ($bannerSlotIds as $slotId) {
            for ($i = 0; $i < self::BANNERS_PER_SLOT; $i++) {
                $id = $nextId++;

                [$startsAt, $endsAt, $isActive] = match ($sequence % 4) {
                    0 => [now()->subDays(10)->toDateString(), now()->addDays(20)->toDateString(), true], // live now
                    1 => [now()->subDays(60)->toDateString(), now()->subDays(30)->toDateString(), true], // flight elapsed
                    2 => [now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString(), true], // scheduled
                    default => [now()->subDays(10)->toDateString(), now()->addDays(20)->toDateString(), false], // deactivated
                };

                $bannerBuffer[] = [
                    'id' => $id,
                    'banner_slot_id' => $slotId,
                    'name' => "Demo Banner {$id}",
                    'advertiser' => 'Demo Advertiser '.(($id % 6) + 1),
                    'destination_link' => "https://advertiser-{$id}.example.test",
                    'display_order' => $sequence,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'is_active' => $isActive,
                    'impressions' => 0,
                    'clicks' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                foreach ($languages as $locale) {
                    $translationBuffer[] = [
                        'banner_id' => $id,
                        'locale' => $locale,
                        'link_text' => "Demo Banner {$id} ({$locale})",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Every third banner untargeted (eligible everywhere), the
                // other two split between territory- and category-targeted
                // — no banner carries both, so the mix reads cleanly on the
                // admin banner list.
                $targetingBucket = $sequence % 3;

                if ($targetingBucket === 1) {
                    $targetBuffer[] = [
                        'banner_id' => $id,
                        'target_type' => Territory::class,
                        'target_id' => $leafTerritoryIds[$sequence % count($leafTerritoryIds)],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } elseif ($targetingBucket === 2) {
                    $targetBuffer[] = [
                        'banner_id' => $id,
                        'target_type' => ObjectType::class,
                        'target_id' => $objectTypeIds[$sequence % count($objectTypeIds)],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $sequence++;
            }
        }

        $this->flush('banners', $bannerBuffer, force: true);
        $this->flush('banner_translations', $translationBuffer, force: true);
        $this->flush('banner_targets', $targetBuffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('banners', 'id'), (select max(id) from banners))");
    }

    /**
     * @param  array<int, string>  $languages
     * @return array<int, int>
     */
    private function seedArticleCategories(array $languages): array
    {
        $nextId = (int) (DB::table('article_categories')->max('id') ?? 0) + 1;
        $categoryIds = [];
        $categoryBuffer = [];
        $translationBuffer = [];

        foreach (self::ARTICLE_CATEGORIES as $order => $category) {
            $id = $nextId++;
            $categoryIds[] = $id;

            $categoryBuffer[] = [
                'id' => $id,
                'slug' => $category['slug'],
                'is_active' => true,
                'display_order' => $order + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach ($category['name'] as $locale => $name) {
                // Only the locales this run's active-language set actually
                // carries — a hardcoded en/ru map outliving the project's
                // own launch-locale list would otherwise try to insert a
                // translation row whose `locale` foreign key has nothing to
                // reference.
                if (! in_array($locale, $languages, true)) {
                    continue;
                }

                $translationBuffer[] = [
                    'article_category_id' => $id,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $this->flush('article_categories', $categoryBuffer, force: true);
        $this->flush('article_category_translations', $translationBuffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('article_categories', 'id'), (select max(id) from article_categories))");

        return $categoryIds;
    }

    /** @return array<int, int> */
    private function seedArticleTags(): array
    {
        $nextId = (int) (DB::table('article_tags')->max('id') ?? 0) + 1;
        $tagIds = [];
        $buffer = [];

        foreach (self::ARTICLE_TAGS as $order => $name) {
            $id = $nextId++;
            $tagIds[] = $id;

            $buffer[] = [
                'id' => $id,
                'slug' => Str::slug($name),
                'name' => $name,
                'is_active' => true,
                'display_order' => $order + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->flush('article_tags', $buffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('article_tags', 'id'), (select max(id) from article_tags))");

        return $tagIds;
    }

    /**
     * Administrator-authored, so every article is simply published or on
     * its way to being published — this model carries no moderation column
     * at all (see the model's own docblock).
     *
     * @param  array<int, string>  $languages
     * @param  array<int, int>  $ownerIds
     * @param  array<int, int>  $articleCategoryIds
     * @param  array<int, int>  $articleTagIds
     * @param  array<int, int>  $leafTerritoryIds
     */
    private function seedArticles(
        array $languages,
        array $ownerIds,
        array $articleCategoryIds,
        array $articleTagIds,
        array $leafTerritoryIds,
        int $firstObjectId,
        int $lastObjectId,
    ): void {
        // Up to three related objects per article — sampled once, up front,
        // rather than one query per article.
        $relatedObjectIds = $this->sampleIdsAcrossRange($firstObjectId, $lastObjectId, self::ARTICLES_COUNT * 3);

        $nextId = (int) (DB::table('articles')->max('id') ?? 0) + 1;
        $articleBuffer = [];
        $translationBuffer = [];
        $objectPivotBuffer = [];
        $territoryPivotBuffer = [];
        $tagPivotBuffer = [];

        for ($i = 0; $i < self::ARTICLES_COUNT; $i++) {
            $id = $nextId++;
            $ownerId = $ownerIds[$id % count($ownerIds)];
            $categoryId = $articleCategoryIds[$i % count($articleCategoryIds)];

            [$status, $publishAt] = match ($i % 5) {
                3 => ['scheduled', now()->addDays($i + 1)->toDateTimeString()],
                4 => ['draft', null],
                default => ['published', now()->subDays($i)->toDateTimeString()],
            };

            $articleBuffer[] = [
                'id' => $id,
                'author_id' => $ownerId,
                'article_category_id' => $categoryId,
                'publish_at' => $publishAt,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach ($languages as $locale) {
                $translationBuffer[] = [
                    'article_id' => $id,
                    'locale' => $locale,
                    'title' => "Demo Article {$id} ({$locale})",
                    'summary' => "Demo article {$id} summary ({$locale}).",
                    'body' => "Demo article {$id} body content ({$locale}).",
                    'slug' => "demo-article-{$id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 0-3 related objects, cycling through the sampled pool rather
            // than reserving a fixed slice per article — a handful of
            // articles legitimately end up with none, matching a real
            // editorial mix.
            $relatedCount = $i % 4;
            for ($r = 0; $r < $relatedCount; $r++) {
                $objectPivotBuffer[] = [
                    'article_id' => $id,
                    'object_id' => $relatedObjectIds[($i * 3 + $r) % count($relatedObjectIds)],
                ];
            }

            $territoryPivotBuffer[] = [
                'article_id' => $id,
                'territory_id' => $leafTerritoryIds[$id % count($leafTerritoryIds)],
            ];

            $tagCount = 1 + ($i % 3);
            for ($t = 0; $t < $tagCount; $t++) {
                $tagPivotBuffer[] = [
                    'article_id' => $id,
                    'article_tag_id' => $articleTagIds[($i + $t) % count($articleTagIds)],
                ];
            }
        }

        $this->flush('articles', $articleBuffer, force: true);
        $this->flush('article_translations', $translationBuffer, force: true);
        $this->flush('article_object', $objectPivotBuffer, force: true);
        $this->flush('article_territory', $territoryPivotBuffer, force: true);
        $this->flush('article_tag', $tagPivotBuffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('articles', 'id'), (select max(id) from articles))");
    }

    /**
     * Owner- or administrator-authored, portal-wide or scoped to one
     * object's page. `moderation_status` and `status` are deliberately
     * varied together, not independently — the same coupling
     * `FiltersModeration`'s own docblock documents as this column's real
     * contract: an approved item is live or on the way to being live,
     * anything else is still sitting in draft.
     *
     * @param  array<int, string>  $languages
     * @param  array<int, int>  $ownerIds
     * @param  array<int, int>  $articleCategoryIds
     * @param  array<int, int>  $leafTerritoryIds
     */
    private function seedNewsItems(
        array $languages,
        array $ownerIds,
        array $articleCategoryIds,
        array $leafTerritoryIds,
        int $firstObjectId,
        int $lastObjectId,
    ): void {
        $sampledObjectIds = $this->sampleIdsAcrossRange($firstObjectId, $lastObjectId, self::NEWS_ITEMS_COUNT);
        $objectTerritories = DB::table('objects')->whereIn('id', $sampledObjectIds)->pluck('territory_id', 'id');

        $nextId = (int) (DB::table('news_items')->max('id') ?? 0) + 1;
        $newsBuffer = [];
        $translationBuffer = [];

        for ($i = 0; $i < self::NEWS_ITEMS_COUNT; $i++) {
            $id = $nextId++;
            $ownerId = $ownerIds[$id % count($ownerIds)];

            // Three in five scoped to one object's page, the rest
            // portal-wide — object_id and territory_id travel together,
            // since a territory-less object-scoped item makes no sense on
            // this model's own terms.
            $hasObject = $i % 5 < 3;
            $objectId = $hasObject ? $sampledObjectIds[$i] : null;
            $territoryId = $hasObject
                ? (int) $objectTerritories[$objectId]
                : $leafTerritoryIds[$i % count($leafTerritoryIds)];

            [$status, $moderationStatus] = match ($i % 5) {
                0, 1 => ['published', 'approved'],
                2 => ['scheduled', 'approved'],
                3 => ['draft', 'pending'],
                default => ['draft', 'rejected'],
            };

            $newsBuffer[] = [
                'id' => $id,
                'author_id' => $ownerId,
                'object_id' => $objectId,
                'territory_id' => $territoryId,
                'article_category_id' => $articleCategoryIds[$i % count($articleCategoryIds)],
                'publish_at' => match ($status) {
                    'published' => now()->subDays($i)->toDateTimeString(),
                    'scheduled' => now()->addDays($i + 1)->toDateTimeString(),
                    default => null,
                },
                'end_at' => null,
                'is_pinned' => $i % 15 === 0,
                'status' => $status,
                'moderation_status' => $moderationStatus,
                'view_count' => $status === 'published' ? $i * 37 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach ($languages as $locale) {
                $translationBuffer[] = [
                    'news_item_id' => $id,
                    'locale' => $locale,
                    'title' => "Demo News {$id} ({$locale})",
                    'summary' => "Demo news {$id} summary ({$locale}).",
                    'body' => "Demo news {$id} body content ({$locale}).",
                    'slug' => "demo-news-{$id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $this->flush('news_items', $newsBuffer, force: true);
        $this->flush('news_translations', $translationBuffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('news_items', 'id'), (select max(id) from news_items))");
    }

    /**
     * Owner- or administrator-authored, always object- and
     * territory-scoped — unlike news items, neither is optional here, so
     * `territory_id` is always the sampled object's own territory rather
     * than an independently chosen one.
     *
     * @param  array<int, string>  $languages
     */
    private function seedPromotions(array $languages, int $firstObjectId, int $lastObjectId): void
    {
        $sampledObjectIds = $this->sampleIdsAcrossRange($firstObjectId, $lastObjectId, self::PROMOTIONS_COUNT);
        $objectTerritories = DB::table('objects')->whereIn('id', $sampledObjectIds)->pluck('territory_id', 'id');

        $nextId = (int) (DB::table('promotions')->max('id') ?? 0) + 1;
        $promotionBuffer = [];
        $translationBuffer = [];

        foreach ($sampledObjectIds as $i => $objectId) {
            $id = $nextId++;
            $territoryId = (int) $objectTerritories[$objectId];

            [$status, $moderationStatus, $startsAt, $endsAt] = match ($i % 5) {
                0, 1 => ['published', 'approved', now()->subDays(10)->toDateString(), now()->addDays(20)->toDateString()],
                2 => ['scheduled', 'approved', now()->addDays(5)->toDateString(), now()->addDays(35)->toDateString()],
                3 => ['archived', 'approved', now()->subDays(60)->toDateString(), now()->subDays(30)->toDateString()],
                default => ['draft', 'pending', now()->addDays(5)->toDateString(), now()->addDays(35)->toDateString()],
            };

            $promotionBuffer[] = [
                'id' => $id,
                'object_id' => $objectId,
                'territory_id' => $territoryId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $status,
                'moderation_status' => $moderationStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach ($languages as $locale) {
                $translationBuffer[] = [
                    'promotion_id' => $id,
                    'locale' => $locale,
                    'title' => "Demo Promotion {$id} ({$locale})",
                    'summary' => "Demo promotion {$id} summary ({$locale}).",
                    'body' => "Demo promotion {$id} body content ({$locale}).",
                    'slug' => "demo-promotion-{$id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $this->flush('promotions', $promotionBuffer, force: true);
        $this->flush('promotion_translations', $translationBuffer, force: true);

        DB::statement("select setval(pg_get_serial_sequence('promotions', 'id'), (select max(id) from promotions))");
    }

    /**
     * Attaches 1-3 reviews to a sample of seeded objects, mixing every
     * moderation status the table carries. There is no separate visitor
     * account pool in this seeder, so a minority of reviews reuse the
     * owner pool as a stand-in for a registered reviewer — schema-valid,
     * not a claim about who leaves reviews for real; the majority are left
     * as a named guest, `author_id` null, which is the more common real
     * shape anyway.
     *
     * @param  array<int, int>  $ownerIds
     */
    private function seedReviews(array $ownerIds, int $firstObjectId, int $lastObjectId): void
    {
        $sampledObjectIds = $this->sampleIdsAcrossRange($firstObjectId, $lastObjectId, self::REVIEWED_OBJECTS_COUNT);
        $objectMeta = DB::table('objects')
            ->whereIn('id', $sampledObjectIds)
            ->get(['id', 'country_id', 'territory_id', 'object_type_id'])
            ->keyBy('id');

        $buffer = [];
        $sequence = 0;

        foreach ($sampledObjectIds as $index => $objectId) {
            $meta = $objectMeta[$objectId]
                ?? throw new RuntimeException("Object {$objectId} not found during demo seeding.");
            $reviewCount = 1 + ($index % 3);

            for ($r = 0; $r < $reviewCount; $r++) {
                $sequence++;
                $isGuest = ($index + $r) % 3 !== 0;

                [$status, $rejectionReason] = match (($index + $r) % 10) {
                    0 => ['pending', null],
                    1 => ['rejected', 'Demo rejection: content did not meet publication guidelines.'],
                    default => ['published', null],
                };

                $hasOwnerReply = $status === 'published' && ($index + $r) % 4 === 0;

                $buffer[] = [
                    'object_id' => $objectId,
                    'country_id' => $meta->country_id,
                    'territory_id' => $meta->territory_id,
                    'object_type_id' => $meta->object_type_id,
                    'rating' => 1 + (($index + $r) % 5),
                    'body' => "Demo review {$sequence} for object {$objectId}.",
                    'author_id' => $isGuest ? null : $ownerIds[$sequence % count($ownerIds)],
                    'author_name' => $isGuest ? "Demo Guest {$sequence}" : null,
                    'owner_reply' => $hasOwnerReply ? "Thank you for staying with us — demo reply {$sequence}." : null,
                    'owner_replied_at' => $hasOwnerReply ? now() : null,
                    'status' => $status,
                    'rejection_reason' => $rejectionReason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $this->flush('reviews', $buffer);
            }
        }

        $this->flush('reviews', $buffer, force: true);
    }

    /**
     * Populates the `audits` table with a small sample of real,
     * schema-correct rows, via {@see AuditJournal} — the same service every
     * other write to this table in this codebase already goes through, not
     * `owen-it/laravel-auditing`'s own automatic model-event observer:
     * that observer gates itself on `App::runningInConsole()`
     * (`config('audit.console')`, false by default) and this class only
     * ever runs as a console command, so a plain `$object->save()` here
     * produces zero rows despite genuinely changing the column — confirmed
     * directly against the package's `AuditableObserver::dispatchAudit()`.
     * `AuditJournal` writes the `Audit` model directly and was built
     * exactly for events the automatic observer cannot reach; a seeded
     * demo event is simply another one of those.
     */
    private function seedAuditTrail(int $firstObjectId, int $lastObjectId): void
    {
        $sampledObjectIds = $this->sampleIdsAcrossRange($firstObjectId, $lastObjectId, self::AUDIT_SAMPLE_COUNT);
        $journal = app(AuditJournal::class);

        foreach ($sampledObjectIds as $objectId) {
            $object = Object_::query()->find($objectId);

            if (! $object instanceof Object_) {
                continue;
            }

            $previousStatus = $object->availability_status;
            $newStatus = $previousStatus === 'available' ? 'unavailable' : 'available';

            // A trivial, side-effect-free column flip — enough to produce
            // one real "updated" audit entry with old/new values, without
            // invoking any of the availability-change business logic this
            // seeder has no reason to exercise.
            $object->forceFill(['availability_status' => $newStatus])->save();

            $journal->record(
                'updated',
                $object,
                ['availability_status' => $previousStatus],
                ['availability_status' => $newStatus],
            );
        }
    }

    /**
     * Deterministically spreads `$count` IDs evenly across `[$firstId,
     * $lastId]` — always the same rows for the same run, so the counts
     * downstream tests assert on stay predictable, rather than a random
     * sample that could occasionally skew toward one end of the range.
     *
     * @return array<int, int>
     */
    private function sampleIdsAcrossRange(int $firstId, int $lastId, int $count): array
    {
        $total = $lastId - $firstId + 1;
        $count = min($count, $total);
        $step = intdiv($total, $count);

        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $firstId + $i * $step;
        }

        return $ids;
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
