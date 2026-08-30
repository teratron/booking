<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A small, curated, PHOTO-COMPLETE dataset for showing the portal to a
 * client — deliberately not {@see DemoVolumeSeeder}, which exists to
 * stress-test the catalog ranking and territory-subtree queries at
 * benchmark scale and attaches no media at all. This seeder does the
 * opposite trade: a couple of dozen real-looking rows, every one of them
 * carrying real photos, so every public page renders complete rather than
 * with broken or missing images. Run explicitly —
 * `artisan db:seed --class=ClientDemoSeeder` — never as part of the default
 * `migrate:fresh --seed`, since it downloads real image bytes over the
 * network and that must never be a dependency of the ordinary dev/test
 * reset loop.
 *
 * Photography comes from Lorem Picsum (piscum.photos), a free-for-any-use
 * placeholder photo service — real, non-broken images without licensing
 * risk, standing in for a client's own property photography until they
 * supply it.
 */
final class ClientDemoSeeder extends Seeder
{
    // Deliberately no WithoutModelEvents: astrotomic/laravel-translatable
    // cascades a translateOrNew() mutation into its own translations table
    // from a `saved` model-event listener, and Spatie Media Library's own
    // conversion pipeline hooks the same way — suppressing model events
    // here would silently save every object, article, and banner with no
    // translation and no media at all, exactly the gap this seeder exists
    // to close.

    /** @var array<string, array<string, array{lat: float, lng: float}>> country code => place name => coordinates */
    private const array TERRITORIES = [
        'MD' => [
            'Chișinău' => ['lat' => 47.0105, 'lng' => 28.8638],
            'Orheiul Vechi' => ['lat' => 47.3667, 'lng' => 28.9333],
        ],
        'UA' => [
            'Lviv' => ['lat' => 49.8397, 'lng' => 24.0297],
            'Odesa' => ['lat' => 46.4825, 'lng' => 30.7233],
        ],
        'GE' => [
            'Tbilisi' => ['lat' => 41.7151, 'lng' => 44.8271],
            'Batumi' => ['lat' => 41.6168, 'lng' => 41.6367],
        ],
    ];

    /**
     * @var list<array{territory: string, type: string, name: array{en: string, ru: string}, short: array{en: string, ru: string}, full: array{en: string, ru: string}, address: string}>
     */
    private const array OBJECTS = [
        [
            'territory' => 'Chișinău', 'type' => 'hotel',
            'name' => ['en' => 'Hotel Bristol Chișinău', 'ru' => 'Отель Бристоль Кишинёв'],
            'short' => ['en' => 'A four-star hotel in the heart of the capital, steps from Ștefan cel Mare Park.', 'ru' => 'Четырёхзвёздочный отель в центре столицы, в двух шагах от парка Штефан чел Маре.'],
            'full' => ['en' => 'Hotel Bristol combines classic Chișinău architecture with modern comfort — spacious rooms, an on-site restaurant, and a rooftop terrace overlooking the old town.', 'ru' => 'Отель Бристоль сочетает классическую кишинёвскую архитектуру с современным комфортом — просторные номера, ресторан при отеле и терраса на крыше с видом на старый город.'],
            'address' => 'Strada Ștefan cel Mare 55, Chișinău',
        ],
        [
            'territory' => 'Chișinău', 'type' => 'guesthouse',
            'name' => ['en' => 'Casa Boierului Guesthouse', 'ru' => 'Гостевой дом Каса Боерулуй'],
            'short' => ['en' => 'A family-run guesthouse in a restored 19th-century townhouse.', 'ru' => 'Семейный гостевой дом в отреставрированном особняке XIX века.'],
            'full' => ['en' => 'Six individually decorated rooms, a courtyard garden, and breakfast made from produce the owners grow themselves — a quieter alternative to the city\'s hotel row.', 'ru' => 'Шесть номеров с индивидуальным оформлением, сад во внутреннем дворе и завтрак из продуктов, выращенных самими хозяевами — тихая альтернатива гостиничным улицам центра.'],
            'address' => 'Strada Mitropolit Varlaam 12, Chișinău',
        ],
        [
            'territory' => 'Chișinău', 'type' => 'apartment',
            'name' => ['en' => 'Central Park Apartments', 'ru' => 'Апартаменты Центральный Парк'],
            'short' => ['en' => 'Self-catering apartments overlooking the central public garden.', 'ru' => 'Апартаменты с собственной кухней, окна выходят на центральный сквер.'],
            'full' => ['en' => 'One- and two-bedroom apartments with full kitchens, fast Wi-Fi, and a dedicated workspace — built for longer stays.', 'ru' => 'Одно- и двухкомнатные апартаменты с полностью оборудованной кухней, быстрым Wi-Fi и рабочим местом — рассчитаны на длительное проживание.'],
            'address' => 'Bulevardul Ștefan cel Mare 65, Chișinău',
        ],
        [
            'territory' => 'Orheiul Vechi', 'type' => 'villa',
            'name' => ['en' => 'Vila Butuceni', 'ru' => 'Вилла Бутучень'],
            'short' => ['en' => 'A stone-built villa on the bluff above the Răut river monastery caves.', 'ru' => 'Вилла из натурального камня на обрыве над пещерным монастырём реки Реут.'],
            'full' => ['en' => 'Traditional Moldovan stonework, a private terrace facing the valley, and a wine cellar stocked from the village\'s own vineyards.', 'ru' => 'Традиционная молдавская каменная кладка, собственная терраса с видом на долину и винный погреб с вином из местных виноградников.'],
            'address' => 'Satul Butuceni, Orheiul Vechi',
        ],
        [
            'territory' => 'Orheiul Vechi', 'type' => 'guesthouse',
            'name' => ['en' => 'Pensiunea Vatra', 'ru' => 'Пансион Ватра'],
            'short' => ['en' => 'A working farmstead guesthouse, three fields back from the monastery trail.', 'ru' => 'Пансион на действующей ферме, в трёх полях от тропы к монастырю.'],
            'full' => ['en' => 'Home-cooked meals, a wood-fired outdoor oven, and horse-drawn cart rides down to the river — the slow-travel option in the valley.', 'ru' => 'Домашняя кухня, летняя печь на дровах и прогулки на телеге к реке — вариант для неспешного отдыха в долине.'],
            'address' => 'Satul Trebujeni, Orheiul Vechi',
        ],
        [
            'territory' => 'Orheiul Vechi', 'type' => 'restaurant',
            'name' => ['en' => 'Terasa Orhei', 'ru' => 'Тераса Орхей'],
            'short' => ['en' => 'An open-air terrace restaurant serving traditional Moldovan dishes.', 'ru' => 'Ресторан с открытой террасой, традиционная молдавская кухня.'],
            'full' => ['en' => 'Grilled mititei, plăcintă, and local wine served on a terrace looking straight down into the river bend.', 'ru' => 'Мититеи на гриле, плацинды и местное вино на террасе с видом прямо на изгиб реки.'],
            'address' => 'Satul Butuceni, Orheiul Vechi',
        ],
        [
            'territory' => 'Lviv', 'type' => 'hotel',
            'name' => ['en' => 'Grand Hotel Lviv', 'ru' => 'Гранд Отель Львов'],
            'short' => ['en' => 'A historic five-star hotel on Svobody Avenue, opposite the Opera House.', 'ru' => 'Исторический пятизвёздочный отель на проспекте Свободы, напротив Оперного театра.'],
            'full' => ['en' => 'Restored 19th-century interiors, a spa floor, and a café terrace with the best people-watching in the old town.', 'ru' => 'Отреставрированные интерьеры XIX века, спа на отдельном этаже и терраса кафе с лучшим видом на прогуливающихся горожан в старом городе.'],
            'address' => 'Prospect Svobody 13, Lviv',
        ],
        [
            'territory' => 'Lviv', 'type' => 'guesthouse',
            'name' => ['en' => 'Rynok Square Guesthouse', 'ru' => 'Гостевой дом на площади Рынок'],
            'short' => ['en' => 'A cosy guesthouse tucked into a courtyard just off the main square.', 'ru' => 'Уютный гостевой дом во внутреннем дворе в двух шагах от главной площади.'],
            'full' => ['en' => 'Exposed brick, an in-room coffee setup from a local roaster, and a five-minute walk to every landmark in the old town.', 'ru' => 'Открытая кирпичная кладка, кофе от местной обжарочной прямо в номере и пять минут пешком до любой достопримечательности старого города.'],
            'address' => 'Ploshcha Rynok 8, Lviv',
        ],
        [
            'territory' => 'Lviv', 'type' => 'cafe',
            'name' => ['en' => 'Lviv Croissant Café', 'ru' => 'Кафе Львівський Круасан'],
            'short' => ['en' => 'A corner café known for its croissants and hand-poured coffee.', 'ru' => 'Угловое кафе, известное круассанами и кофе ручной пуровки.'],
            'full' => ['en' => 'A small menu done well: fresh pastry each morning, single-origin coffee, and window seats over the cobblestones.', 'ru' => 'Небольшое, но продуманное меню: свежая выпечка каждое утро, кофе одного региона происхождения и места у окна с видом на брусчатку.'],
            'address' => 'Vulytsia Rynok 22, Lviv',
        ],
        [
            'territory' => 'Odesa', 'type' => 'hotel',
            'name' => ['en' => 'Hotel Odesa Bay', 'ru' => 'Отель Одесса Бэй'],
            'short' => ['en' => 'A seafront hotel a short walk from the Potemkin Stairs.', 'ru' => 'Отель на набережной, в нескольких минутах ходьбы от Потёмкинской лестницы.'],
            'full' => ['en' => 'Sea-view rooms, a rooftop pool, and direct beach access — Odesa\'s promenade life from your own balcony.', 'ru' => 'Номера с видом на море, бассейн на крыше и прямой выход к пляжу — вся набережная жизнь Одессы с собственного балкона.'],
            'address' => 'Lanzheronivska Street 2, Odesa',
        ],
        [
            'territory' => 'Odesa', 'type' => 'apartment',
            'name' => ['en' => 'Deribasivska Apartments', 'ru' => 'Апартаменты на Дерибасовской'],
            'short' => ['en' => 'Serviced apartments right on Odesa\'s main pedestrian street.', 'ru' => 'Апартаменты с обслуживанием прямо на главной пешеходной улице Одессы.'],
            'full' => ['en' => 'Renovated 19th-century courtyard buildings, now bright one-bedroom apartments with everything in walking distance.', 'ru' => 'Отреставрированные дворовые здания XIX века — светлые однокомнатные апартаменты, откуда всё в пешей доступности.'],
            'address' => 'Vulytsia Derybasivska 17, Odesa',
        ],
        [
            'territory' => 'Odesa', 'type' => 'villa',
            'name' => ['en' => 'Sea Breeze Villa', 'ru' => 'Вилла Морской Бриз'],
            'short' => ['en' => 'A private villa on the Fontanka coastline with its own stretch of beach.', 'ru' => 'Частная вилла на побережье Фонтанки с собственным участком пляжа.'],
            'full' => ['en' => 'Four bedrooms, a covered terrace facing the water, and a private path down to the sea — booked whole, not by the room.', 'ru' => 'Четыре спальни, крытая терраса с видом на воду и собственная тропа к морю — сдаётся целиком, а не по номерам.'],
            'address' => 'Fontanska Doroha 85, Odesa',
        ],
        [
            'territory' => 'Tbilisi', 'type' => 'hotel',
            'name' => ['en' => 'Tbilisi Old Town Hotel', 'ru' => 'Отель Тбилиси Олд Таун'],
            'short' => ['en' => 'A boutique hotel among the sulphur bathhouses of Abanotubani.', 'ru' => 'Бутик-отель среди серных бань района Абанотубани.'],
            'full' => ['en' => 'Balconies looking straight up at Narikala Fortress, and the old town\'s bakeries and wine bars all within a few minutes\' walk.', 'ru' => 'Балконы с видом прямо на крепость Нарикала, а пекарни и винные бары старого города — в нескольких минутах ходьбы.'],
            'address' => 'Abanotubani Street 4, Tbilisi',
        ],
        [
            'territory' => 'Tbilisi', 'type' => 'guesthouse',
            'name' => ['en' => 'Sulphur Baths Guesthouse', 'ru' => 'Гостевой дом у Серных Бань'],
            'short' => ['en' => 'A family guesthouse two minutes from the public sulphur baths.', 'ru' => 'Семейный гостевой дом в двух минутах от общественных серных бань.'],
            'full' => ['en' => 'Home-style Georgian breakfast every morning, and a rooftop terrace that catches the sunset over the old town roofs.', 'ru' => 'Домашний грузинский завтрак каждое утро и терраса на крыше, откуда виден закат над крышами старого города.'],
            'address' => 'Grishashvili Street 9, Tbilisi',
        ],
        [
            'territory' => 'Tbilisi', 'type' => 'apartment',
            'name' => ['en' => 'Rustaveli Business Apartments', 'ru' => 'Бизнес-апартаменты на Руставели'],
            'short' => ['en' => 'Modern serviced apartments on Tbilisi\'s central avenue.', 'ru' => 'Современные апартаменты с обслуживанием на центральном проспекте Тбилиси.'],
            'full' => ['en' => 'A dedicated desk, fast Wi-Fi, and a fully equipped kitchen — built for the extended business stay, not just the weekend trip.', 'ru' => 'Отдельное рабочее место, быстрый Wi-Fi и полностью оборудованная кухня — рассчитаны на длительную деловую поездку, а не только выходные.'],
            'address' => 'Rustaveli Avenue 34, Tbilisi',
        ],
        [
            'territory' => 'Batumi', 'type' => 'hotel',
            'name' => ['en' => 'Batumi Boulevard Hotel', 'ru' => 'Отель Батумский Бульвар'],
            'short' => ['en' => 'A beachfront hotel directly on Batumi\'s palm-lined boulevard.', 'ru' => 'Отель на первой линии, прямо на батумском бульваре с пальмами.'],
            'full' => ['en' => 'Sea-view rooms above the boulevard\'s cafés and fountains, with the cable car up Anuria Mountain a short walk away.', 'ru' => 'Номера с видом на море над кафе и фонтанами бульвара, а канатная дорога на гору Анурия — в нескольких минутах ходьбы.'],
            'address' => 'Batumi Boulevard 8, Batumi',
        ],
        [
            'territory' => 'Batumi', 'type' => 'villa',
            'name' => ['en' => 'Villa Adjara', 'ru' => 'Вилла Аджария'],
            'short' => ['en' => 'A hillside villa above Batumi with a private pool and sea view.', 'ru' => 'Вилла на склоне холма над Батуми с собственным бассейном и видом на море.'],
            'full' => ['en' => 'Three bedrooms, an infinity pool facing the Black Sea, and a garden of citrus trees typical of the Adjara coast.', 'ru' => 'Три спальни, бассейн с видом на бесконечность, выходящий на Чёрное море, и сад из цитрусовых деревьев, характерных для побережья Аджарии.'],
            'address' => 'Tsikhisdziri Road 3, Batumi',
        ],
        [
            'territory' => 'Batumi', 'type' => 'sanatorium',
            'name' => ['en' => 'Batumi Seaside Sanatorium', 'ru' => 'Приморский санаторий Батуми'],
            'short' => ['en' => 'A health resort offering mineral-water and climate therapy on the coast.', 'ru' => 'Курорт с минерально-водной и климатотерапией на побережье.'],
            'full' => ['en' => 'Full-board treatment programmes, a therapeutic pool, and daily walks along the sea — a longer-stay wellness option on the Adjara coast.', 'ru' => 'Полный пансион с лечебными программами, терапевтический бассейн и ежедневные прогулки вдоль моря — вариант оздоровительного отдыха на длительный срок на побережье Аджарии.'],
            'address' => 'Kobuleti Highway 21, Batumi',
        ],
    ];

    public function run(): void
    {
        /** @var list<string> $languages */
        $languages = DB::table('languages')->where('is_active', true)->pluck('code')->all();
        $countryIds = DB::table('countries')->pluck('id', 'code');
        $objectTypeIds = DB::table('object_types')->pluck('id', 'key');
        $bannerSlotIds = DB::table('banner_slots')->pluck('id', 'key');

        $territoryIds = $this->seedTerritories($countryIds, $languages);
        $owners = $this->seedOwners();
        $objects = $this->seedObjects($territoryIds, $objectTypeIds, $owners);
        $this->seedReviews($objects);
        $this->seedBanners($bannerSlotIds);

        $categoryIds = $this->seedArticleCategories($languages);
        $this->seedArticles($languages, $owners, $categoryIds);
        $this->seedNewsItems($languages, $owners, $categoryIds, $objects, $territoryIds);
        $this->seedPromotions($objects, $territoryIds);
    }

    /**
     * @param  Collection<string, int>  $countryIds
     * @param  list<string>  $languages
     * @return array<string, int> territory name => id
     */
    private function seedTerritories(Collection $countryIds, array $languages): array
    {
        $levelOneIdByCountry = DB::table('territory_levels')
            ->where('depth_rank', 1)
            ->pluck('id', 'country_id');

        $ids = [];

        foreach (self::TERRITORIES as $countryCode => $places) {
            $countryId = $countryIds[$countryCode];
            $levelId = $levelOneIdByCountry[$countryId];

            foreach ($places as $name => $coords) {
                $territoryId = DB::table('territories')->insertGetId([
                    'country_id' => $countryId,
                    'level_id' => $levelId,
                    'latitude' => $coords['lat'],
                    'longitude' => $coords['lng'],
                    'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$coords['lng']}, {$coords['lat']}), 4326)"),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $slug = Str::slug($name);

                foreach ($languages as $locale) {
                    DB::table('territory_translations')->insert([
                        'territory_id' => $territoryId,
                        'country_id' => $countryId,
                        'locale' => $locale,
                        'name' => $name,
                        'slug' => $slug,
                        'full_slug_path' => $slug,
                        'published_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $ids[$name] = $territoryId;
            }
        }

        return $ids;
    }

    /** @return array<int, int> owner user ids */
    private function seedOwners(): array
    {
        $names = ['Elena Popescu', 'Andriy Melnyk', 'Nino Beridze', 'Ion Ceban'];

        return collect($names)->map(fn (string $name, int $i): int => User::query()->create([
            'name' => $name,
            'email' => 'owner'.($i + 1).'@demo.booking.test',
            'password' => Hash::make(Str::random(32)),
        ])->id)->all();
    }

    /**
     * @param  array<string, int>  $territoryIds
     * @param  Collection<string, int>  $objectTypeIds
     * @param  array<int, int>  $owners
     * @return list<int> created object ids
     */
    private function seedObjects(array $territoryIds, Collection $objectTypeIds, array $owners): array
    {
        $countryIdByTerritory = DB::table('territories')->pluck('country_id', 'id');
        $created = [];

        foreach (self::OBJECTS as $i => $data) {
            $territoryId = $territoryIds[$data['territory']];

            /** @var Object_ $object */
            $object = Object_::query()->create([
                'ulid' => (string) Str::ulid(),
                'owner_id' => $owners[$i % count($owners)],
                'object_type_id' => $objectTypeIds[$data['type']],
                'territory_id' => $territoryId,
                'country_id' => $countryIdByTerritory[$territoryId],
                'address' => $data['address'],
                'status' => 'published',
                'moderation_status' => 'approved',
                'availability_status' => 'available',
            ]);

            foreach (['en', 'ru'] as $locale) {
                $object->translateOrNew($locale)->fill([
                    'name' => $data['name'][$locale],
                    'short_description' => $data['short'][$locale],
                    'full_description' => $data['full'][$locale],
                    'slug' => Str::slug($data['name']['en']),
                    'published_at' => now(),
                ]);
            }
            $object->save();

            $seedBase = Str::slug($data['name']['en']);
            for ($photo = 1; $photo <= 3; $photo++) {
                $object->addMediaFromUrl("https://picsum.photos/seed/{$seedBase}-{$photo}/1200/800")
                    ->withCustomProperties(['is_primary' => $photo === 1])
                    ->toMediaCollection('photos');
            }

            $created[] = $object->id;
        }

        return $created;
    }

    /** @param  list<int>  $objectIds */
    private function seedReviews(array $objectIds): void
    {
        $samples = [
            ['rating' => 5, 'name' => 'Maria K.', 'body' => 'Wonderful stay — exactly as described, and the staff went out of their way to help us plan the rest of the trip.'],
            ['rating' => 4, 'name' => 'David T.', 'body' => 'Great location and a comfortable room. Breakfast could have had more variety, but everything else was excellent.'],
            ['rating' => 5, 'name' => 'Sophie L.', 'body' => 'Booked again on our next visit without a second thought. Quiet, clean, and very well run.'],
        ];

        foreach ($objectIds as $objectId) {
            foreach ($samples as $review) {
                Review::query()->create([
                    'object_id' => $objectId,
                    'rating' => $review['rating'],
                    'body' => $review['body'],
                    'author_name' => $review['name'],
                    'status' => 'published',
                ]);
            }
        }
    }

    /** @param  Collection<string, int>  $bannerSlotIds */
    private function seedBanners(Collection $bannerSlotIds): void
    {
        $banners = [
            ['slot' => 'home-top', 'name' => 'Summer escape', 'advertiser' => 'Booking Portal', 'seed' => 'banner-home-top'],
            ['slot' => 'home-mid', 'name' => 'Mountain getaways', 'advertiser' => 'Booking Portal', 'seed' => 'banner-home-mid'],
            ['slot' => 'home-bottom', 'name' => 'Book direct and save', 'advertiser' => 'Booking Portal', 'seed' => 'banner-home-bottom'],
        ];

        foreach ($banners as $data) {
            /** @var Banner $banner */
            $banner = Banner::query()->create([
                'banner_slot_id' => $bannerSlotIds[$data['slot']],
                'name' => $data['name'],
                'advertiser' => $data['advertiser'],
                'destination_link' => '/',
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addYear(),
                'is_active' => true,
            ]);

            foreach (['en', 'ru'] as $locale) {
                $banner->translateOrNew($locale)->fill([
                    'link_text' => $locale === 'en' ? 'Learn more' : 'Подробнее',
                ]);
            }
            $banner->save();

            $banner->addMediaFromUrl("https://picsum.photos/seed/{$data['seed']}-desktop/1600/400")
                ->toMediaCollection('desktop_creative');
            $banner->addMediaFromUrl("https://picsum.photos/seed/{$data['seed']}-mobile/800/600")
                ->toMediaCollection('mobile_creative');
        }
    }

    /**
     * @param  list<string>  $languages
     * @return array<int, int>
     */
    private function seedArticleCategories(array $languages): array
    {
        $categories = [
            ['slug' => 'travel-tips', 'name' => ['en' => 'Travel Tips', 'ru' => 'Советы путешественникам']],
            ['slug' => 'destinations', 'name' => ['en' => 'Destinations', 'ru' => 'Направления']],
        ];

        return collect($categories)->map(function (array $data) use ($languages): int {
            /** @var ArticleCategory $category */
            $category = ArticleCategory::query()->create(['slug' => $data['slug'], 'is_active' => true]);

            foreach ($languages as $locale) {
                if (isset($data['name'][$locale])) {
                    $category->translateOrNew($locale)->fill(['name' => $data['name'][$locale]]);
                }
            }
            $category->save();

            return $category->id;
        })->all();
    }

    /**
     * @param  list<string>  $languages
     * @param  array<int, int>  $owners
     * @param  array<int, int>  $categoryIds
     */
    private function seedArticles(array $languages, array $owners, array $categoryIds): void
    {
        $articles = [
            [
                'title' => ['en' => 'Five Days Between Chișinău and the Orhei Monastery', 'ru' => 'Пять дней между Кишинёвом и монастырём Орхей'],
                'summary' => ['en' => 'A short itinerary linking the capital to the cave monastery at Orheiul Vechi.', 'ru' => 'Короткий маршрут, связывающий столицу с пещерным монастырём Оргеюл Векь.'],
                'body' => ['en' => "Start in Chișinău's old town, then head northeast to the limestone cliffs above the Răut river, where a working monastery has occupied the same caves since the fifteenth century.", 'ru' => 'Начните со старого города Кишинёва, затем отправляйтесь на северо-восток к известняковым скалам над рекой Реут, где в тех же пещерах с XV века находится действующий монастырь.'],
                'seed' => 'article-orhei',
            ],
            [
                'title' => ['en' => "Lviv's Coffee Culture, One Café at a Time", 'ru' => 'Кофейная культура Львова, кафе за кафе'],
                'summary' => ['en' => 'Why the city takes its coffee as seriously as its architecture.', 'ru' => 'Почему город относится к кофе так же серьёзно, как к своей архитектуре.'],
                'body' => ['en' => "Lviv's coffeehouse tradition dates back centuries, and the old town's side streets still hide roasters worth a detour.", 'ru' => 'Кофейная традиция Львова насчитывает несколько веков, а на боковых улочках старого города до сих пор скрываются обжарочные, ради которых стоит сделать крюк.'],
                'seed' => 'article-lviv-coffee',
            ],
            [
                'title' => ['en' => "A Weekend on Batumi's Boulevard", 'ru' => 'Выходные на батумском бульваре'],
                'summary' => ['en' => 'Palm trees, a cable car, and the Black Sea coast.', 'ru' => 'Пальмы, канатная дорога и побережье Чёрного моря.'],
                'body' => ['en' => 'The seven-kilometre boulevard is Batumi\'s main stage — cafés, fountains, and a cable car climbing straight up from the seafront.', 'ru' => 'Семикилометровый бульвар — главная сцена Батуми: кафе, фонтаны и канатная дорога, поднимающаяся прямо от набережной.'],
                'seed' => 'article-batumi',
            ],
        ];

        foreach ($articles as $i => $data) {
            /** @var Article $article */
            $article = Article::query()->create([
                'author_id' => $owners[$i % count($owners)],
                'article_category_id' => $categoryIds[$i % count($categoryIds)],
                'publish_at' => now()->subDays($i + 1),
                'status' => 'published',
            ]);

            foreach ($languages as $locale) {
                if (isset($data['title'][$locale])) {
                    $article->translateOrNew($locale)->fill([
                        'title' => $data['title'][$locale],
                        'summary' => $data['summary'][$locale],
                        'body' => $data['body'][$locale],
                        'slug' => Str::slug($data['title']['en']),
                        'published_at' => now(),
                    ]);
                }
            }
            $article->save();

            $article->addMediaFromUrl("https://picsum.photos/seed/{$data['seed']}/1200/700")
                ->toMediaCollection('cover_image');
        }
    }

    /**
     * @param  list<string>  $languages
     * @param  array<int, int>  $owners
     * @param  array<int, int>  $categoryIds
     * @param  list<int>  $objectIds
     * @param  array<string, int>  $territoryIds
     */
    private function seedNewsItems(array $languages, array $owners, array $categoryIds, array $objectIds, array $territoryIds): void
    {
        $items = [
            [
                'title' => ['en' => 'Hotel Bristol Opens Its Rooftop Terrace for the Season', 'ru' => 'Отель Бристоль открывает террасу на крыше в этом сезоне'],
                'summary' => ['en' => 'The seasonal terrace reopens with a new evening menu.', 'ru' => 'Сезонная терраса открывается вновь с новым вечерним меню.'],
                'body' => ['en' => 'The rooftop terrace at Hotel Bristol Chișinău reopens this month, with views over the old town and a refreshed evening menu.', 'ru' => 'Терраса на крыше отеля Бристоль Кишинёв снова открывается в этом месяце — с видом на старый город и обновлённым вечерним меню.'],
                'seed' => 'news-bristol-terrace',
                'territory' => 'Chișinău',
            ],
            [
                'title' => ['en' => 'New Direct Trail to Villa Adjara from the Coastal Road', 'ru' => 'Новая прямая тропа к вилле Аджария с прибрежной дороги'],
                'summary' => ['en' => 'A shorter walking route now connects the coastal road to the villa.', 'ru' => 'Более короткий пеший маршрут теперь соединяет прибрежную дорогу с виллой.'],
                'body' => ['en' => 'A new marked trail cuts the walk from the coastal road up to Villa Adjara by half, with the same sea view the whole way.', 'ru' => 'Новая размеченная тропа сокращает путь от прибрежной дороги до виллы Аджария вдвое, сохраняя тот же вид на море на всём протяжении.'],
                'seed' => 'news-adjara-trail',
                'territory' => 'Batumi',
            ],
        ];

        foreach ($items as $i => $data) {
            /** @var NewsItem $item */
            $item = NewsItem::query()->create([
                'author_id' => $owners[$i % count($owners)],
                'object_id' => $objectIds[$i] ?? null,
                'territory_id' => $territoryIds[$data['territory']],
                'article_category_id' => $categoryIds[$i % count($categoryIds)],
                'publish_at' => now()->subDays($i + 1),
                'is_pinned' => false,
                'status' => 'published',
                'moderation_status' => 'approved',
            ]);

            foreach ($languages as $locale) {
                if (isset($data['title'][$locale])) {
                    $item->translateOrNew($locale)->fill([
                        'title' => $data['title'][$locale],
                        'summary' => $data['summary'][$locale],
                        'body' => $data['body'][$locale],
                        'slug' => Str::slug($data['title']['en']),
                        'published_at' => now(),
                    ]);
                }
            }
            $item->save();

            $item->addMediaFromUrl("https://picsum.photos/seed/{$data['seed']}/1200/700")
                ->toMediaCollection('cover_image');
        }
    }

    /**
     * @param  list<int>  $objectIds
     * @param  array<string, int>  $territoryIds
     */
    private function seedPromotions(array $objectIds, array $territoryIds): void
    {
        $promotions = [
            [
                'title' => ['en' => 'Stay 3 Nights, Pay for 2', 'ru' => 'Три ночи по цене двух'],
                'summary' => ['en' => 'A limited early-booking offer on select rooms.', 'ru' => 'Ограниченное предложение при раннем бронировании отдельных номеров.'],
                'body' => ['en' => 'Book three consecutive nights and the third is on the house — valid for stays booked at least two weeks in advance.', 'ru' => 'Забронируйте три ночи подряд, и третья — за наш счёт. Действует при бронировании минимум за две недели.'],
                'seed' => 'promo-3for2',
                'territory' => 'Chișinău',
            ],
        ];

        foreach ($promotions as $data) {
            /** @var Promotion $promotion */
            $promotion = Promotion::query()->create([
                'object_id' => $objectIds[0],
                'territory_id' => $territoryIds[$data['territory']],
                'starts_at' => now()->subWeek(),
                'ends_at' => now()->addMonths(2),
                'status' => 'published',
                'moderation_status' => 'approved',
            ]);

            foreach (['en', 'ru'] as $locale) {
                $promotion->translateOrNew($locale)->fill([
                    'title' => $data['title'][$locale],
                    'summary' => $data['summary'][$locale],
                    'body' => $data['body'][$locale],
                    'slug' => Str::slug($data['title']['en']),
                    'published_at' => now(),
                ]);
            }
            $promotion->save();

            $promotion->addMediaFromUrl("https://picsum.photos/seed/{$data['seed']}/1200/700")
                ->toMediaCollection('image');
        }
    }
}
