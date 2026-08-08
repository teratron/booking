<?php

declare(strict_types=1);

return [

    'brand' => 'Portal Administration',
    'cabinet_brand' => 'Owner Cabinet',

    'bulk' => [
        'confirmation' => 'This affects :count selected record(s). The change applies to every one of them.',
        'confirm_label' => 'Apply to :count record(s)',
    ],

    'impersonation' => [
        'banner_text' => 'Support mode — you are viewing the cabinet as :owner.',
        'return_to_admin' => 'Return to admin',
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
            'current_status' => 'Current status',
            'changed_at' => 'Changed at',
            'changed_by' => 'Changed by',
            'last_confirmed_at' => 'Last confirmed',
            'override' => 'Override availability',
            'new_status' => 'New status',
            'comment' => 'Comment',
            'revert' => 'Revert to previous',
            'revert_refused' => 'Nothing to revert to.',
            'history_title' => 'Availability history',
            'from_status' => 'From',
            'to_status' => 'To',
            'source' => 'Source',
            'sources' => [
                'owner' => 'Owner',
                'administrator' => 'Administrator',
                'automatic' => 'Automatic',
            ],
        ],

        'filters' => [
            'contact' => 'Phone, email or messenger',
            'quick_available' => 'Vacancies available',
            'quick_unspecified' => 'Status unspecified',
            'quick_stale' => 'Status not updated recently',
            'quick_active' => 'Active objects',
            'quick_expired_package' => 'Expired package',
        ],

        'bulk' => [
            'publish' => 'Publish',
            'hide' => 'Hide',
            'draft' => 'Set to draft',
            'archive' => 'Archive',
            'assign_promotion_label' => 'Assign promotional label',
            'move_territory' => 'Move to another territory',
            'assign_manager' => 'Assign a manager',
            'notify_owners' => 'Notify owners',
            'export' => 'Export selection',
            'reset_stale_availability' => 'Reset stale availability',
            'promotion_label' => 'Promotional label',
            'starts_at' => 'Starts on',
            'ends_at' => 'Ends on',
            'territory' => 'Target territory',
            'manager' => 'Manager',
            'notification_title' => 'Message title',
            'notification_body' => 'Message body',
            'queued' => 'Queued for processing. You will be notified once it completes.',
            'completed' => 'Done.',
            'scope_denied' => 'Selection refused',
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
                'availability' => 'Availability',
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

    'owners' => [
        'title' => 'Owners',
        'model_label' => 'owner',

        'columns' => [
            'name' => 'Name',
            'company' => 'Company',
            'phone' => 'Phone',
            'email' => 'Email',
            'country' => 'Country',
            'objects_count' => 'Objects',
            'overdue_placements' => 'Overdue placements',
            'registered_at' => 'Registered',
            'last_sign_in_at' => 'Last sign-in',
            'status' => 'Status',
        ],

        'status' => [
            'active' => 'Active',
            'blocked' => 'Blocked',
        ],

        'filters' => [
            'status_all' => 'All',
        ],

        'form' => [
            'name' => 'Name',
            'email' => 'Email',
            'company' => 'Company',
            'phone' => 'Phone',
            'country' => 'Country',
            'out_of_scope' => 'This country is outside your assigned scope.',
        ],

        'actions' => [
            'impersonate' => 'Enter support mode',
            'impersonate_confirm' => 'You will be signed in to the cabinet panel as this owner. Every action you take there is journalled against your own account, not theirs.',
            'impersonation_refused' => 'Support mode refused.',
            'block' => 'Block',
            'block_confirm' => 'This refuses the account admission to every panel until restored.',
            'restore' => 'Restore',
            'restore_confirm' => 'This lifts the suspension and restores panel access.',
            'send_password_reset_link' => 'Send password reset link',
            'password_reset_link_sent' => 'Password reset link sent.',
            'applied' => 'Done.',
        ],

        'objects' => [
            'title' => 'Attached objects',
            'attach' => 'Attach object',
            'object' => 'Object',
            'detach' => 'Detach',
            'detach_confirm' => 'This clears the object\'s owner. It becomes ownerless until reassigned.',
            'detachment_refused' => 'Detachment refused',
            'attachment_refused' => 'Attachment refused — that object is outside your assigned scope.',
            'applied' => 'Done.',
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

    'languages' => [
        'title' => 'Languages',
        'model_label' => 'language',

        'columns' => [
            'code' => 'Code',
            'short_label' => 'Label',
            'text_direction' => 'Direction',
            'active' => 'Active',
            'primary' => 'Primary',
            'display_order' => 'Order',
        ],

        'text_direction' => [
            'ltr' => 'Left to right',
            'rtl' => 'Right to left',
        ],

        'actions' => [
            'activate' => 'Activate',
            'deactivate' => 'Deactivate',
            'make_primary' => 'Make primary',
        ],

        'notifications' => [
            'activated' => 'Language activated.',
            'deactivated' => 'Language deactivated.',
            'deactivate_refused' => 'Could not deactivate this language',
            'primary_changed' => 'Primary language updated.',
            'make_primary_refused' => 'Could not change the primary language',
        ],
    ],

    'interface_catalog' => [
        'title' => 'Interface catalog',
        'save' => 'Save catalog',
        'saved' => 'Interface catalog saved.',
    ],

];
