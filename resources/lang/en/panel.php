<?php

declare(strict_types=1);

return [

    'brand' => 'Portal Administration',
    'cabinet_brand' => 'Owner Cabinet',

    'bulk' => [
        'confirmation' => 'This affects :count selected record(s). The change applies to every one of them.',
        'confirm_label' => 'Apply to :count record(s)',
    ],

    'navigation' => [
        'catalog' => 'Catalog',
        'geography' => 'Geography',
        'governance' => 'Governance',
        'access' => 'Access',
        'system' => 'System',
    ],

    'settings' => [
        'title' => 'Portal settings',
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
        'critical_refused' => 'Restricted setting',

        'groups' => [
            'portal' => 'Portal',
            'presentation' => 'Presentation',
            'media' => 'Media',
            'moderation' => 'Moderation',
            'availability' => 'Availability',
            'placement' => 'Placement',
            'notifications' => 'Notifications',
            'integrations' => 'Integrations',
            'security' => 'Security',
            'journal' => 'Action journal',
        ],

        'fields' => [
            'portal.name' => 'Portal name',
            'portal.logo_path' => 'Logo path',
            'portal.contact_email' => 'Contact email',
            'portal.contact_phone' => 'Contact phone',

            'presentation.date_format' => 'Date format',
            'presentation.time_format' => 'Time format',
            'presentation.timezone' => 'Time zone',
            'presentation.default_currency' => 'Default currency',
            'presentation.within_tier_order' => 'Ordering within a placement tier',

            'media.image_max_width' => 'Maximum image width (px)',
            'media.image_max_height' => 'Maximum image height (px)',
            'media.upload_max_kilobytes' => 'Maximum upload size (KB)',
            'media.allowed_mime_types' => 'Accepted image types',

            'moderation.default_mode' => 'Default moderation mode',
            'moderation.moderated_change_types' => 'Change types that require review',
            'moderation.partial_acceptance_enabled' => 'Allow accepting part of a change set',
            'moderation.stale_object_days' => 'Days before an object is considered stale',

            'availability.confirmation_period_days' => 'Days before availability needs re-confirming',
            'availability.auto_reset_enabled' => 'Automatically reset stale availability',

            'placement.expiry_grace_days' => 'Grace period after placement expiry (days)',
            'placement.expired_behaviour' => 'Behaviour on placement expiry',

            'notifications.digest_hour' => 'Hour the daily digest is sent',
            'notifications.expiry_reminder_lead_days' => 'Days of notice before expiry',

            'integrations.map_tile_provider' => 'Map tile provider',
            'integrations.map_tile_key' => 'Map tile API key',
            'integrations.captcha_provider' => 'CAPTCHA provider',
            'integrations.captcha_site_key' => 'CAPTCHA site key',
            'integrations.captcha_secret' => 'CAPTCHA secret',
            'integrations.analytics_measurement_id' => 'Analytics measurement ID',

            'security.session_lifetime_minutes' => 'Session lifetime (minutes)',
            'security.sign_in_max_attempts' => 'Sign-in attempts before lockout',

            'journal.retention_days' => 'Journal retention (days)',
        ],
    ],

];
