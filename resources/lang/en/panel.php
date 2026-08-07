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

    'dashboard' => [
        'objects_total' => 'Objects',
        'objects_breakdown' => ':published published · :hidden hidden · :archived archived',
        'pending_moderation' => 'Awaiting review',
        'reporting_vacancies' => 'Reporting vacancies',
        'owners' => 'Owners',
        'geography_breakdown' => ':countries countries · :territories territories',
        'recorded_amount' => 'Recorded payments',
        'recorded_amount_window' => 'Last 30 days',
        'active_placements' => 'Active placements',
        'expiring_placements' => 'Expiring within 14 days',
    ],

    'objects' => [
        'title' => 'Objects',
        'model_label' => 'object',

        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'country' => 'Country',
            'territory' => 'Territory',
            'owner' => 'Owner',
            'status' => 'Publication',
            'moderation_status' => 'Moderation',
            'availability' => 'Availability',
            'availability_confirmed' => 'Availability confirmed',
            'identifier' => 'Identifier',
        ],

        'status' => [
            'draft' => 'Draft',
            'published' => 'Published',
            'hidden' => 'Hidden',
            'archived' => 'Archived',
        ],

        'moderation' => [
            'pending' => 'Awaiting review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'revision_requested' => 'Revision requested',
        ],

        'availability' => [
            'available' => 'Vacancies available',
            'unavailable' => 'No vacancies',
            'unspecified' => 'Not stated',
        ],

        'filters' => [
            'contact' => 'Phone, email or messenger',
        ],

        'form' => [
            'tabs' => [
                'core' => 'Core information',
                'geography' => 'Geography',
                'type_attributes' => 'Type-specific fields',
                'seo' => 'SEO',
                'contacts' => 'Contacts',
                'services' => 'Services',
                'owner_staff' => 'Owner & staff',
            ],
            'name' => 'Name',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'address' => 'Address',
            'seo_slug' => 'URL slug',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'contact_value' => 'Value',
            'contact_label' => 'Label',
            'out_of_scope' => 'This country or category is outside your assigned scope.',
        ],

        'lifecycle' => [
            'save_as_draft' => 'Save as draft',
            'publish' => 'Publish',
            'hide' => 'Hide',
            'return_for_revision' => 'Return for revision',
            'archive' => 'Archive',
            'restore' => 'Restore',
            'duplicate' => 'Duplicate',
            'transfer_ownership' => 'Transfer ownership',
            'applied' => 'Done.',
            'section' => 'Section needing revision',
            'reason' => 'Reason',
            'new_owner' => 'New owner',
        ],
    ],

    'territories' => [
        'title' => 'Territories',
        'model_label' => 'territory',

        'columns' => [
            'name' => 'Name',
            'level' => 'Level',
            'country' => 'Country',
            'parent' => 'Parent',
            'active' => 'Active',
        ],

        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],

        'form' => [
            'display_order' => 'Display order',
            'name' => 'Name',
            'slug' => 'URL slug',
            'short_description' => 'Short description',
            'seo_title' => 'SEO title',
        ],

        'actions' => [
            'reparent' => 'Move to another parent',
            'new_parent' => 'New parent territory',
            'no_parent' => 'None — make this a country root',
            'reparent_confirm' => 'This moves :descendants descendant territories and :objects attached objects.',
            'cycle_refused' => 'Move refused',
        ],
    ],

    'object_types' => [
        'title' => 'Object Types',
        'model_label' => 'object type',

        'columns' => [
            'active' => 'Active',
        ],

        'form' => [
            'key' => 'Key',
            'name' => 'Name',
            'parent' => 'Parent type',
            'no_parent' => 'None — top-level type',
            'icon' => 'Icon path',
            'has_rooms' => 'Has rooms',
            'has_availability_status' => 'Has availability status',
            'display_order' => 'Display order',
            'amenity_groups' => 'Applicable amenity groups',
            'attribute_schema' => 'Custom fields',
            'attribute_schema_hint' => 'The extra fields an object of this type exposes on its own form and public page — e.g. cuisine and opening hours for a dining type.',
            'attribute_key' => 'Field key',
            'attribute_type' => 'Field type',
            'attribute_types' => [
                'text' => 'Text',
                'number' => 'Number',
                'boolean' => 'Yes / No',
            ],
            'attribute_label' => 'Label (:language)',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
        ],
    ],

    'modules' => [
        'title' => 'Modules',
        'model_label' => 'module',
        'applied' => 'Module state updated.',
        'refused' => 'Change refused',
        'confirm' => 'Setting :module at :scope scope affects :count object(s).',

        'scope' => [
            'portal' => 'portal',
            'country' => 'country',
        ],

        'state' => [
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
        ],

        'columns' => [
            'key' => 'Key',
            'name' => 'Module',
            'default_state' => 'Default',
            'effective' => 'Effective (portal)',
            'dependencies' => 'Requires',
            'registered' => 'Registered',
        ],

        'actions' => [
            'enable_portal' => 'Enable portal-wide',
            'disable_portal' => 'Disable portal-wide',
            'set_for_country' => 'Set for a country',
        ],

        'fields' => [
            'country' => 'Country',
            'state' => 'State',
        ],
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
