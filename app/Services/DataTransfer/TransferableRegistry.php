<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

use App\Models\Amenity;
use App\Models\Banner;
use App\Models\ContactChannel;
use App\Models\FinancialRecord;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\PlacementPackage;
use App\Models\Price;
use App\Models\Promotion;
use App\Models\StatDaily;
use App\Models\Territory;
use App\Models\User;
use InvalidArgumentException;
use OwenIt\Auditing\Models\Audit;

/**
 * The single declaration of every entity kind the back office may import or
 * export, per the thirteen kinds the specification names identically for
 * both directions: objects, owners, contacts, prices, services, geographic
 * reference data, packages, payments, banners, news, promotions, statistics,
 * and the action journal.
 *
 * Import and export are readers of this list, never independent inventories
 * of their own — a column present in one but not the other is exactly the
 * drift this registry exists to make impossible. Every export action and
 * every import column-mapping step resolves its column set from here.
 */
final class TransferableRegistry
{
    /** @var array<string, Transferable>|null */
    private static ?array $cache = null;

    /** @return array<string, Transferable> */
    public static function all(): array
    {
        return self::$cache ??= self::build();
    }

    public static function get(string $key): Transferable
    {
        return self::all()[$key]
            ?? throw new InvalidArgumentException("Unknown transferable data-type key: [{$key}].");
    }

    /** @return array<string, Transferable> */
    private static function build(): array
    {
        $formats = ['xlsx', 'csv', 'json'];

        $entries = [
            new Transferable(
                key: 'objects',
                label: __('panel.data_transfer.kinds.objects'),
                model: Object_::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.objects.id'), 'integer'),
                    new TransferableColumn('ulid', __('panel.data_transfer.columns.objects.ulid'), 'string'),
                    new TransferableColumn('owner_id', __('panel.data_transfer.columns.objects.owner_id'), 'integer'),
                    new TransferableColumn('object_type_id', __('panel.data_transfer.columns.objects.object_type_id'), 'integer'),
                    new TransferableColumn('territory_id', __('panel.data_transfer.columns.objects.territory_id'), 'integer'),
                    new TransferableColumn('country_id', __('panel.data_transfer.columns.objects.country_id'), 'integer'),
                    new TransferableColumn('address', __('panel.data_transfer.columns.objects.address'), 'string'),
                    new TransferableColumn('latitude', __('panel.data_transfer.columns.objects.latitude'), 'decimal'),
                    new TransferableColumn('longitude', __('panel.data_transfer.columns.objects.longitude'), 'decimal'),
                    new TransferableColumn('status', __('panel.data_transfer.columns.objects.status'), 'string'),
                    new TransferableColumn('availability_status', __('panel.data_transfer.columns.objects.availability_status'), 'string'),
                ],
                formats: $formats,
                permission: 'object.export',
            ),
            new Transferable(
                key: 'owners',
                label: __('panel.data_transfer.kinds.owners'),
                model: User::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.owners.id'), 'integer'),
                    new TransferableColumn('name', __('panel.data_transfer.columns.owners.name'), 'string', isPersonalData: true),
                    new TransferableColumn('email', __('panel.data_transfer.columns.owners.email'), 'string', isPersonalData: true),
                    new TransferableColumn('phone', __('panel.data_transfer.columns.owners.phone'), 'string', isPersonalData: true),
                    new TransferableColumn('company', __('panel.data_transfer.columns.owners.company'), 'string', isPersonalData: true),
                    new TransferableColumn('country_id', __('panel.data_transfer.columns.owners.country_id'), 'integer'),
                    new TransferableColumn('locale', __('panel.data_transfer.columns.owners.locale'), 'string'),
                    new TransferableColumn('blocked_at', __('panel.data_transfer.columns.owners.blocked_at'), 'datetime'),
                ],
                formats: $formats,
                permission: 'user.export',
                requiresPersonalDataPermission: true,
            ),
            new Transferable(
                key: 'contacts',
                label: __('panel.data_transfer.kinds.contacts'),
                model: ContactChannel::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.contacts.id'), 'integer'),
                    new TransferableColumn('object_id', __('panel.data_transfer.columns.contacts.object_id'), 'integer'),
                    new TransferableColumn('contact_channel_type_id', __('panel.data_transfer.columns.contacts.contact_channel_type_id'), 'integer'),
                    new TransferableColumn('raw_value', __('panel.data_transfer.columns.contacts.raw_value'), 'string', isPersonalData: true),
                    new TransferableColumn('derived_link', __('panel.data_transfer.columns.contacts.derived_link'), 'string', isPersonalData: true),
                    new TransferableColumn('label', __('panel.data_transfer.columns.contacts.label'), 'string'),
                    new TransferableColumn('display_order', __('panel.data_transfer.columns.contacts.display_order'), 'integer'),
                    new TransferableColumn('is_active', __('panel.data_transfer.columns.contacts.is_active'), 'boolean'),
                ],
                formats: $formats,
                permission: 'object.export',
                requiresPersonalDataPermission: true,
            ),
            new Transferable(
                key: 'prices',
                label: __('panel.data_transfer.kinds.prices'),
                model: Price::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.prices.id'), 'integer'),
                    new TransferableColumn('priceable_type', __('panel.data_transfer.columns.prices.priceable_type'), 'string'),
                    new TransferableColumn('priceable_id', __('panel.data_transfer.columns.prices.priceable_id'), 'integer'),
                    new TransferableColumn('type', __('panel.data_transfer.columns.prices.type'), 'string'),
                    new TransferableColumn('calculation_unit', __('panel.data_transfer.columns.prices.calculation_unit'), 'string'),
                    new TransferableColumn('amount', __('panel.data_transfer.columns.prices.amount'), 'decimal', isFinancial: true),
                    new TransferableColumn('currency', __('panel.data_transfer.columns.prices.currency'), 'string'),
                    new TransferableColumn('valid_from', __('panel.data_transfer.columns.prices.valid_from'), 'date'),
                    new TransferableColumn('valid_until', __('panel.data_transfer.columns.prices.valid_until'), 'date'),
                ],
                formats: $formats,
                permission: 'object.export',
            ),
            new Transferable(
                key: 'services',
                label: __('panel.data_transfer.kinds.services'),
                model: Amenity::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.services.id'), 'integer'),
                    new TransferableColumn('amenity_group_id', __('panel.data_transfer.columns.services.amenity_group_id'), 'integer'),
                    new TransferableColumn('icon_path', __('panel.data_transfer.columns.services.icon_path'), 'string'),
                    new TransferableColumn('is_filterable', __('panel.data_transfer.columns.services.is_filterable'), 'boolean'),
                    new TransferableColumn('is_active', __('panel.data_transfer.columns.services.is_active'), 'boolean'),
                    new TransferableColumn('display_order', __('panel.data_transfer.columns.services.display_order'), 'integer'),
                ],
                formats: $formats,
                permission: 'object.export',
            ),
            new Transferable(
                key: 'geography',
                label: __('panel.data_transfer.kinds.geography'),
                model: Territory::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.geography.id'), 'integer'),
                    new TransferableColumn('parent_id', __('panel.data_transfer.columns.geography.parent_id'), 'integer'),
                    new TransferableColumn('country_id', __('panel.data_transfer.columns.geography.country_id'), 'integer'),
                    new TransferableColumn('level_id', __('panel.data_transfer.columns.geography.level_id'), 'integer'),
                    new TransferableColumn('latitude', __('panel.data_transfer.columns.geography.latitude'), 'decimal'),
                    new TransferableColumn('longitude', __('panel.data_transfer.columns.geography.longitude'), 'decimal'),
                    new TransferableColumn('is_active', __('panel.data_transfer.columns.geography.is_active'), 'boolean'),
                    new TransferableColumn('display_order', __('panel.data_transfer.columns.geography.display_order'), 'integer'),
                ],
                formats: $formats,
                permission: 'geography.export',
            ),
            new Transferable(
                key: 'packages',
                label: __('panel.data_transfer.kinds.packages'),
                model: PlacementPackage::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.packages.id'), 'integer'),
                    new TransferableColumn('placement_tier_id', __('panel.data_transfer.columns.packages.placement_tier_id'), 'integer'),
                    new TransferableColumn('object_type_id', __('panel.data_transfer.columns.packages.object_type_id'), 'integer'),
                    new TransferableColumn('price', __('panel.data_transfer.columns.packages.price'), 'decimal', isFinancial: true),
                    new TransferableColumn('currency', __('panel.data_transfer.columns.packages.currency'), 'string'),
                    new TransferableColumn('validity_days', __('panel.data_transfer.columns.packages.validity_days'), 'integer'),
                    new TransferableColumn('bump_allowed', __('panel.data_transfer.columns.packages.bump_allowed'), 'boolean'),
                    new TransferableColumn('bump_interval_hours', __('panel.data_transfer.columns.packages.bump_interval_hours'), 'integer'),
                    new TransferableColumn('free_bumps_per_period', __('panel.data_transfer.columns.packages.free_bumps_per_period'), 'integer'),
                    new TransferableColumn('paid_bump_price', __('panel.data_transfer.columns.packages.paid_bump_price'), 'decimal', isFinancial: true),
                    new TransferableColumn('is_active', __('panel.data_transfer.columns.packages.is_active'), 'boolean'),
                ],
                formats: $formats,
                permission: 'commerce.export',
            ),
            new Transferable(
                key: 'payments',
                label: __('panel.data_transfer.kinds.payments'),
                model: FinancialRecord::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.payments.id'), 'integer'),
                    new TransferableColumn('object_id', __('panel.data_transfer.columns.payments.object_id'), 'integer'),
                    new TransferableColumn('banner_id', __('panel.data_transfer.columns.payments.banner_id'), 'integer'),
                    new TransferableColumn('service', __('panel.data_transfer.columns.payments.service'), 'string'),
                    new TransferableColumn('placement_package_id', __('panel.data_transfer.columns.payments.placement_package_id'), 'integer'),
                    new TransferableColumn('amount', __('panel.data_transfer.columns.payments.amount'), 'decimal', isFinancial: true),
                    new TransferableColumn('currency', __('panel.data_transfer.columns.payments.currency'), 'string'),
                    new TransferableColumn('paid_at', __('panel.data_transfer.columns.payments.paid_at'), 'datetime'),
                    new TransferableColumn('valid_from', __('panel.data_transfer.columns.payments.valid_from'), 'date'),
                    new TransferableColumn('valid_until', __('panel.data_transfer.columns.payments.valid_until'), 'date'),
                    new TransferableColumn('payment_method', __('panel.data_transfer.columns.payments.payment_method'), 'string', isFinancial: true),
                    new TransferableColumn('document_number', __('panel.data_transfer.columns.payments.document_number'), 'string', isFinancial: true),
                    new TransferableColumn('status', __('panel.data_transfer.columns.payments.status'), 'string'),
                    new TransferableColumn('responsible_staff_id', __('panel.data_transfer.columns.payments.responsible_staff_id'), 'integer'),
                ],
                formats: $formats,
                permission: 'finance.export',
                requiresFinancialPermission: true,
            ),
            new Transferable(
                key: 'banners',
                label: __('panel.data_transfer.kinds.banners'),
                model: Banner::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.banners.id'), 'integer'),
                    new TransferableColumn('banner_slot_id', __('panel.data_transfer.columns.banners.banner_slot_id'), 'integer'),
                    new TransferableColumn('name', __('panel.data_transfer.columns.banners.name'), 'string'),
                    new TransferableColumn('advertiser', __('panel.data_transfer.columns.banners.advertiser'), 'string'),
                    new TransferableColumn('destination_link', __('panel.data_transfer.columns.banners.destination_link'), 'string'),
                    new TransferableColumn('starts_at', __('panel.data_transfer.columns.banners.starts_at'), 'date'),
                    new TransferableColumn('ends_at', __('panel.data_transfer.columns.banners.ends_at'), 'date'),
                    new TransferableColumn('is_active', __('panel.data_transfer.columns.banners.is_active'), 'boolean'),
                    new TransferableColumn('impressions', __('panel.data_transfer.columns.banners.impressions'), 'integer'),
                    new TransferableColumn('clicks', __('panel.data_transfer.columns.banners.clicks'), 'integer'),
                ],
                formats: $formats,
                permission: 'advertising.export',
            ),
            new Transferable(
                key: 'news',
                label: __('panel.data_transfer.kinds.news'),
                model: NewsItem::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.news.id'), 'integer'),
                    new TransferableColumn('author_id', __('panel.data_transfer.columns.news.author_id'), 'integer'),
                    new TransferableColumn('object_id', __('panel.data_transfer.columns.news.object_id'), 'integer'),
                    new TransferableColumn('territory_id', __('panel.data_transfer.columns.news.territory_id'), 'integer'),
                    new TransferableColumn('article_category_id', __('panel.data_transfer.columns.news.article_category_id'), 'integer'),
                    new TransferableColumn('publish_at', __('panel.data_transfer.columns.news.publish_at'), 'datetime'),
                    new TransferableColumn('end_at', __('panel.data_transfer.columns.news.end_at'), 'datetime'),
                    new TransferableColumn('is_pinned', __('panel.data_transfer.columns.news.is_pinned'), 'boolean'),
                    new TransferableColumn('status', __('panel.data_transfer.columns.news.status'), 'string'),
                    new TransferableColumn('moderation_status', __('panel.data_transfer.columns.news.moderation_status'), 'string'),
                    new TransferableColumn('view_count', __('panel.data_transfer.columns.news.view_count'), 'integer'),
                ],
                formats: $formats,
                permission: 'content.export',
            ),
            new Transferable(
                key: 'promotions',
                label: __('panel.data_transfer.kinds.promotions'),
                model: Promotion::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.promotions.id'), 'integer'),
                    new TransferableColumn('object_id', __('panel.data_transfer.columns.promotions.object_id'), 'integer'),
                    new TransferableColumn('territory_id', __('panel.data_transfer.columns.promotions.territory_id'), 'integer'),
                    new TransferableColumn('starts_at', __('panel.data_transfer.columns.promotions.starts_at'), 'date'),
                    new TransferableColumn('ends_at', __('panel.data_transfer.columns.promotions.ends_at'), 'date'),
                    new TransferableColumn('status', __('panel.data_transfer.columns.promotions.status'), 'string'),
                    new TransferableColumn('moderation_status', __('panel.data_transfer.columns.promotions.moderation_status'), 'string'),
                ],
                formats: $formats,
                permission: 'content.export',
            ),
            new Transferable(
                key: 'statistics',
                label: __('panel.data_transfer.kinds.statistics'),
                model: StatDaily::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.statistics.id'), 'integer'),
                    new TransferableColumn('date', __('panel.data_transfer.columns.statistics.date'), 'date'),
                    new TransferableColumn('subject_type', __('panel.data_transfer.columns.statistics.subject_type'), 'string'),
                    new TransferableColumn('subject_id', __('panel.data_transfer.columns.statistics.subject_id'), 'integer'),
                    new TransferableColumn('kind', __('panel.data_transfer.columns.statistics.kind'), 'string'),
                    new TransferableColumn('contact_channel_type_id', __('panel.data_transfer.columns.statistics.contact_channel_type_id'), 'integer'),
                    new TransferableColumn('territory_id', __('panel.data_transfer.columns.statistics.territory_id'), 'integer'),
                    new TransferableColumn('country_id', __('panel.data_transfer.columns.statistics.country_id'), 'integer'),
                    new TransferableColumn('locale', __('panel.data_transfer.columns.statistics.locale'), 'string'),
                    new TransferableColumn('count', __('panel.data_transfer.columns.statistics.count'), 'integer'),
                ],
                formats: $formats,
                permission: 'analytics.export',
            ),
            new Transferable(
                key: 'action_journal',
                label: __('panel.data_transfer.kinds.action_journal'),
                model: Audit::class,
                columns: [
                    new TransferableColumn('id', __('panel.data_transfer.columns.action_journal.id'), 'integer'),
                    new TransferableColumn('event', __('panel.data_transfer.columns.action_journal.event'), 'string'),
                    new TransferableColumn('auditable_type', __('panel.data_transfer.columns.action_journal.auditable_type'), 'string'),
                    new TransferableColumn('auditable_id', __('panel.data_transfer.columns.action_journal.auditable_id'), 'integer'),
                    new TransferableColumn('user_type', __('panel.data_transfer.columns.action_journal.user_type'), 'string'),
                    new TransferableColumn('user_id', __('panel.data_transfer.columns.action_journal.user_id'), 'integer'),
                    new TransferableColumn('old_values', __('panel.data_transfer.columns.action_journal.old_values'), 'json'),
                    new TransferableColumn('new_values', __('panel.data_transfer.columns.action_journal.new_values'), 'json'),
                    new TransferableColumn('ip_address', __('panel.data_transfer.columns.action_journal.ip_address'), 'string', isPersonalData: true),
                    new TransferableColumn('user_agent', __('panel.data_transfer.columns.action_journal.user_agent'), 'string'),
                    new TransferableColumn('created_at', __('panel.data_transfer.columns.action_journal.created_at'), 'datetime'),
                ],
                formats: $formats,
                permission: 'audit.export',
            ),
        ];

        $byKey = [];
        foreach ($entries as $entry) {
            $byKey[$entry->key] = $entry;
        }

        return $byKey;
    }
}
