<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * Every runtime-configurable portal parameter, declared once.
 *
 * The governing requirement is that monetization, presentation, and structural
 * parameters change "without a programmer". A setting absent from this list is
 * a setting that needs a deployment, so the list is the actual measure of
 * whether that requirement is met — not the settings screen, which only
 * renders what is declared here.
 *
 * Two of the requirement's parameters are deliberately absent: languages and
 * countries are registries with their own tables, editing screens, and
 * translations, not scalar settings. Pointing at them from here would create a
 * second, competing source of truth.
 */
final class SettingsRegistry
{
    /** @return array<string, SettingDefinition> */
    public function all(): array
    {
        $definitions = [];

        foreach ($this->declarations() as $declaration) {
            $definitions[$declaration->key] = $declaration;
        }

        return $definitions;
    }

    public function get(string $key): ?SettingDefinition
    {
        return $this->all()[$key] ?? null;
    }

    /** @return list<string> */
    public function groups(): array
    {
        return array_values(array_unique(array_map(
            static fn (SettingDefinition $definition): string => $definition->group,
            $this->declarations(),
        )));
    }

    /**
     * @param  string  $group  one of the values returned by groups()
     * @return list<SettingDefinition>
     */
    public function inGroup(string $group): array
    {
        return array_values(array_filter(
            $this->declarations(),
            static fn (SettingDefinition $definition): bool => $definition->group === $group,
        ));
    }

    /** @return list<SettingDefinition> */
    private function declarations(): array
    {
        return [
            new SettingDefinition('portal.name', 'portal', 'string', 'Tourism Portal'),
            new SettingDefinition('portal.logo_path', 'portal', 'string', ''),
            new SettingDefinition('portal.contact_email', 'portal', 'string', ''),
            new SettingDefinition('portal.contact_phone', 'portal', 'string', ''),
            // Footer social links — empty means the icon is omitted, not a
            // dead link to a blank profile.
            new SettingDefinition('portal.social_instagram_url', 'portal', 'string', ''),
            new SettingDefinition('portal.social_telegram_url', 'portal', 'string', ''),
            new SettingDefinition('portal.social_facebook_url', 'portal', 'string', ''),

            new SettingDefinition('presentation.date_format', 'presentation', 'string', 'd.m.Y'),
            new SettingDefinition('presentation.time_format', 'presentation', 'string', 'H:i'),
            new SettingDefinition('presentation.timezone', 'presentation', 'string', 'UTC'),
            new SettingDefinition('presentation.default_currency', 'presentation', 'string', 'EUR'),
            // Placement tier is always the primary sort key; this names only
            // the tiebreaker applied *within* a tier, which is the sole part
            // of the ordering contract an administrator may change.
            new SettingDefinition('presentation.within_tier_order', 'presentation', 'string', 'recent_bump'),

            new SettingDefinition('media.image_max_width', 'media', 'int', 2560),
            new SettingDefinition('media.image_max_height', 'media', 'int', 1440),
            new SettingDefinition('media.upload_max_kilobytes', 'media', 'int', 8192),
            new SettingDefinition('media.allowed_mime_types', 'media', 'array', ['image/jpeg', 'image/png', 'image/webp']),
            // The owner cabinet's own photo-gallery cap — see
            // `ObjectPhotoService`, its first consumer.
            new SettingDefinition('media.object_photos_max_count', 'media', 'int', 30),

            new SettingDefinition('moderation.default_mode', 'moderation', 'string', 'review'),
            new SettingDefinition('moderation.moderated_change_types', 'moderation', 'array', [
                'name', 'description', 'photographs', 'prices', 'contacts', 'links',
                'news', 'promotions', 'category', 'location', 'services', 'status',
            ]),
            // Off by default: shipping field-level acceptance on would make
            // every moderator decision a field-by-field one.
            new SettingDefinition('moderation.partial_acceptance_enabled', 'moderation', 'bool', false),
            new SettingDefinition('moderation.stale_object_days', 'moderation', 'int', 180),

            // Above this many selected records, a bulk operation dispatches
            // to a queued job instead of running inline in the request —
            // the inline path is a fine default for the ordinary case, but
            // a request that blocks on thousands of row mutations is the
            // wrong cost to impose on an administrator's browser tab.
            new SettingDefinition('back_office.bulk_queue_threshold', 'back_office', 'int', 1000),

            new SettingDefinition('availability.confirmation_period_days', 'availability', 'int', 14),
            // Off by default: resetting a stale "no vacancies" back to
            // "available" manufactures exactly the false claim the feature
            // exists to prevent.
            new SettingDefinition('availability.auto_reset_enabled', 'availability', 'bool', false),

            new SettingDefinition('placement.expiry_grace_days', 'placement', 'int', 3),
            new SettingDefinition('placement.expired_behaviour', 'placement', 'string', 'demote'),
            // The pre-expiry/day-of points of §62's six-point warning
            // schedule; the sixth point ("after") reuses expiry_grace_days
            // above rather than a second offset list.
            new SettingDefinition('placement.expiry_warning_offset_days', 'placement', 'array', [30, 14, 7, 3, 0]),

            new SettingDefinition('notifications.digest_hour', 'notifications', 'int', 9),
            new SettingDefinition('notifications.expiry_reminder_lead_days', 'notifications', 'int', 7),
            // Sweep-level retry budget for a dispatch that reached `failed`
            // after DispatchNotificationJob's own queue-level tries were
            // exhausted — separate limit, separate concern: that job's
            // tries absorb a few seconds of transient trouble, this one
            // covers a channel down for longer than that.
            new SettingDefinition('notifications.dispatch_max_retries', 'notifications', 'int', 3),
            // Maximum administrator broadcasts (see BroadcastComposer) per
            // calendar day. Portal-wide rather than per-administrator: the
            // recipient-facing cost of an over-broadcast portal is the same
            // regardless of which staff account sent the message.
            new SettingDefinition('notifications.broadcast_rate_limit', 'notifications', 'int', 5),

            // Defaults read from config/booking.php's own 'integrations'
            // section, itself sourced from .env — a fresh clone with
            // MAP_TILE_KEY/CAPTCHA_* already set in .env works without an
            // administrator visiting the settings screen first. Still only
            // a default: a stored administrator override still wins, the
            // same as every other setting here.
            new SettingDefinition('integrations.map_tile_provider', 'integrations', 'string', config('booking.integrations.map_tile_provider', 'maptiler')),
            new SettingDefinition('integrations.map_tile_key', 'integrations', 'string', config('booking.integrations.map_tile_key', ''), isCritical: true),
            new SettingDefinition('integrations.captcha_provider', 'integrations', 'string', config('booking.integrations.captcha_provider', 'none')),
            new SettingDefinition('integrations.captcha_site_key', 'integrations', 'string', config('booking.integrations.captcha_site_key', '')),
            new SettingDefinition('integrations.captcha_secret', 'integrations', 'string', config('booking.integrations.captcha_secret', ''), isCritical: true),
            new SettingDefinition('integrations.analytics_measurement_id', 'integrations', 'string', ''),

            new SettingDefinition('security.session_lifetime_minutes', 'security', 'int', 120, isCritical: true),
            new SettingDefinition('security.sign_in_max_attempts', 'security', 'int', 5, isCritical: true),

            new SettingDefinition('journal.retention_days', 'journal', 'int', 730, isCritical: true),

            // How long a raw stat_events row survives after its day has
            // been rolled up into stat_dailies — see AnalyticsCompactionJob.
            new SettingDefinition('analytics.raw_retention_days', 'analytics', 'int', 90),

            // The public API's own per-token ceiling when a token's own
            // `rate_limit_per_minute` is left unset at issuance — see
            // ApiTokenService::issue().
            new SettingDefinition('api.default_rate_limit_per_minute', 'api', 'int', 60),

            // The Open Graph image fallback for an entity with neither an
            // explicit override nor its own cover photo — see
            // MetadataResolver, which reaches this only after both are absent.
            new SettingDefinition('seo.default_og_image', 'seo', 'string', ''),
            // Appended verbatim as its own line in robots.txt, after the
            // fixed Disallow rules RobotsController always emits.
            new SettingDefinition('seo.robots_extra', 'seo', 'string', ''),
        ];
    }
}
