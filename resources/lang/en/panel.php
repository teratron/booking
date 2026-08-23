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
        'commerce' => 'Commerce',
        'advertising' => 'Advertising',
        'analytics' => 'Analytics',
        'communication' => 'Communication',
        'content' => 'Content',
        'geography' => 'Geography',
        'seo' => 'SEO',
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
            'missing_translation' => 'Missing translation in',
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
            'seo_canonical_url' => 'Canonical URL override',
            'seo_indexable' => 'Indexable',
            'seo_og_title' => 'Open Graph title',
            'seo_og_description' => 'Open Graph description',
            'seo_og_image' => 'Open Graph image URL',
            'slug_claimed_by_redirect' => 'The address :path is already claimed by an active redirect — this language\'s change was not saved. Retire the redirect first if this reuse is intentional.',
            'contact_type' => 'Type',
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
            'permanently_delete' => 'Permanently delete',
            'permanently_delete_refused' => 'Could not permanently delete this object',
            'reauthenticate_password' => 'Confirm your password to continue',
            'duplicate' => 'Duplicate',
            'transfer_ownership' => 'Transfer ownership',
            'applied' => 'Done.',
            'section' => 'Section needing revision',
            'reason' => 'Reason',
            'new_owner' => 'New owner',
            'bump' => 'Bump',
            'bump_comment' => 'Comment',
            'bump_refused' => 'Could not bump this object',
            'merge' => 'Merge duplicate',
        ],

        'merge' => [
            'other_object' => 'Duplicate object',
            'other_pending' => 'Choose the duplicate object above first.',
            'survivor' => 'Which record should survive?',
            'confirm_heading' => 'Merge these two objects?',
            'summary' => ':survivor will survive. :merged will be archived, and its own page will permanently redirect here.',
            'refused' => 'This merge could not be completed',
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
            'seo_description' => 'SEO description',
            'seo_canonical_url' => 'Canonical URL override',
            'seo_indexable' => 'Indexable',
            'seo_og_title' => 'Open Graph title',
            'seo_og_description' => 'Open Graph description',
            'seo_og_image' => 'Open Graph image URL',
            'slug_claimed_by_redirect' => 'Slug change refused — the resulting address is already claimed by an active redirect.',
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
            'slug' => 'URL slug',
            'parent' => 'Parent type',
            'no_parent' => 'None — top-level type',
            'icon' => 'Icon path',
            'has_rooms' => 'Has rooms',
            'has_availability_status' => 'Has availability status',
            'structured_data_kind' => 'Structured data type',
            'structured_data_kinds' => [
                'lodging' => 'Lodging (accommodation)',
                'food' => 'Food establishment (dining)',
                'place' => 'Place (attraction or other)',
            ],
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
            'seo_canonical_url' => 'Canonical URL override',
            'seo_indexable' => 'Indexable',
            'seo_og_title' => 'Open Graph title',
            'seo_og_description' => 'Open Graph description',
            'seo_og_image' => 'Open Graph image URL',
        ],
    ],

    'redirects' => [
        'title' => 'Redirects',
        'model_label' => 'redirect',
        'columns' => [
            'updated_at' => 'Last updated',
        ],
        'form' => [
            'locale' => 'Language',
            'from_path' => 'From path',
            'from_path_hint' => 'The old address, without the language segment — e.g. md/old-region-slug or o/old-object-slug.',
            'to_path' => 'To path',
            'to_path_hint' => 'The current address a visitor following the old link should land on.',
            'active' => 'Active',
        ],
    ],

    'seo' => [
        'indexable_default' => 'Default (follow the site policy)',
        'indexable_yes' => 'Force indexable',
        'indexable_no' => 'Force noindex',
    ],

    'seo_metadata_templates' => [
        'title' => 'SEO Metadata Templates',
        'model_label' => 'SEO metadata template',
        'columns' => [
            'updated_at' => 'Last updated',
        ],
        'form' => [
            'entity_type' => 'Entity type',
            'entity_types' => [
                'territory' => 'Territory',
                'object_type' => 'Object type',
                'object' => 'Object',
                'promotion' => 'Promotion',
                'news_item' => 'News item',
                'article' => 'Article',
            ],
            'locale' => 'Language',
            'field' => 'Field',
            'fields' => [
                'title' => 'Title',
                'description' => 'Description',
            ],
            'template' => 'Template',
            'template_hint' => 'Placeholders: {name} for the entity\'s own name or title, {territory} for its territory\'s name where one applies. Used only when the entity has no explicit override of its own.',
        ],
    ],

    'catalog_filter_promotions' => [
        'title' => 'Catalog Filter Promotions',
        'model_label' => 'catalog filter promotion',
        'columns' => [
            'updated_at' => 'Last updated',
        ],
        'form' => [
            'signature' => 'Filter signature',
            'signature_hint' => 'The single filter this promotion makes indexable, as key=value — e.g. object_type=hotel. A catalog view stays indexable only with zero active filters or exactly one promoted filter; any other combination is never indexed.',
            'active' => 'Active',
        ],
    ],

    'error_pages' => [
        'title' => 'Error Pages',
        'model_label' => 'error page',
        'columns' => [
            'updated_at' => 'Last updated',
        ],
        'status_names' => [
            '404' => '404 — Page not found',
        ],
        'form' => [
            'status_code' => 'Status code',
            'translations' => 'Content',
            'title' => 'Title',
            'body' => 'Body',
        ],
    ],

    'seo_health' => [
        'title' => 'SEO Health',
        'navigation_label' => 'Health Dashboard',
        'map_tile_key_missing' => 'No map tile provider key is configured — the public map renders a placeholder instead of live tiles. Set MAP_TILE_KEY in .env or configure a key from the settings screen.',
        'columns' => [
            'entity_type' => 'Entity type',
            'locale' => 'Language',
            'name' => 'Entity',
        ],
        'warnings' => [
            'missing_title' => 'Missing SEO title',
            'missing_description' => 'Missing SEO description',
            'over_length_title' => 'SEO title over 60 characters',
            'excluded_from_indexing' => 'Excluded from indexing',
            'duplicate_address' => 'Duplicate canonical URL',
            'missing_translation' => 'Missing translation',
        ],
        'empty_state' => 'No issues found for this check.',
        'missing_translation_count' => '{1} :count entity missing this translation|[2,*] :count entities missing this translation',
    ],

    'placement_tiers' => [
        'title' => 'Placement Tiers',
        'model_label' => 'placement tier',

        'columns' => [
            'rank' => 'Rank',
            'active' => 'Active',
        ],

        'form' => [
            'rank' => 'Rank',
            'label' => 'Label',
            'badge_text' => 'Badge text',
            'border_colour' => 'Border colour',
            'badge_colour' => 'Badge colour',
            'badge_icon' => 'Badge icon',
        ],
    ],

    'placement_packages' => [
        'title' => 'Placement Packages',
        'model_label' => 'placement package',

        'columns' => [
            'active' => 'Active',
        ],

        'form' => [
            'name' => 'Name',
            'tier' => 'Placement tier',
            'object_type' => 'Object category',
            'any_object_type' => 'Any category',
            'price' => 'Price',
            'currency' => 'Currency',
            'validity_days' => 'Validity (days)',
            'bump_allowed' => 'Bumping allowed',
            'bump_interval_hours' => 'Minimum interval between free bumps (hours)',
            'free_bumps_per_period' => 'Free bumps per period',
            'paid_bump_price' => 'Paid bump price',
            'display_order' => 'Display order',
        ],
    ],

    'banner_slots' => [
        'title' => 'Banner Slots',
        'model_label' => 'banner slot',

        'columns' => [
            'active' => 'Active',
        ],

        'form' => [
            'key' => 'Key',
            'name' => 'Name',
            'surfaces' => 'Surfaces',
            'surface_options' => [
                'home' => 'Home',
                'country' => 'Country',
                'region' => 'Region',
                'city' => 'City',
                'resort' => 'Resort',
                'category' => 'Category',
                'object' => 'Object',
                'news' => 'News',
                'article' => 'Article',
            ],
        ],
    ],

    'banners' => [
        'title' => 'Banners',
        'model_label' => 'banner',

        'columns' => [
            'active' => 'Active',
            'impressions' => 'Impressions',
            'clicks' => 'Clicks',
            'click_through_rate' => 'CTR',
        ],

        'form' => [
            'slot' => 'Slot',
            'name' => 'Name',
            'advertiser' => 'Advertiser',
            'destination_link' => 'Destination link',
            'starts_at' => 'Starts on',
            'ends_at' => 'Ends on',
            'display_order' => 'Display order',
            'desktop_creative' => 'Desktop creative',
            'mobile_creative' => 'Mobile creative',
            'territories' => 'Territories',
            'categories' => 'Categories',
            'target_languages' => 'Languages',
            'link_text' => 'Link text',
        ],
    ],

    'promotion_labels' => [
        'title' => 'Promotional Labels',
        'model_label' => 'promotional label',

        'columns' => [
            'active' => 'Active',
        ],

        'form' => [
            'text' => 'Text',
            'border_colour' => 'Border colour',
            'text_colour' => 'Text colour',
            'background_colour' => 'Background colour',
            'icon' => 'Icon',
            'position_on_card' => 'Position on card',
            'preview' => 'Card preview',
            'preview_placeholder' => 'Choose a position to see the preview.',
            'preview_sample_text' => 'Label',
        ],

        'positions' => [
            'top-left' => 'Top-left corner',
            'top-right' => 'Top-right corner',
            'bottom-left' => 'Bottom-left corner',
            'bottom-right' => 'Bottom-right corner',
        ],
    ],

    'financial_records' => [
        'title' => 'Financial Ledger',
        'model_label' => 'ledger entry',

        'form' => [
            'subject_kind' => 'Applies to',
            'subject_object' => 'Object',
            'subject_banner' => 'Banner',
            'object' => 'Object',
            'banner' => 'Banner',
            'service' => 'Service',
            'package' => 'Package',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'status' => 'Status',
            'paid_at' => 'Paid at',
            'valid_from' => 'Valid from',
            'valid_until' => 'Valid until',
            'payment_method' => 'Payment method',
            'document_number' => 'Document number',
            'responsible_staff' => 'Responsible staff',
            'comment' => 'Comment',
        ],

        'status' => [
            'awaiting_payment' => 'Awaiting payment',
            'paid' => 'Paid',
            'partially_paid' => 'Partially paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
            'granted_free' => 'Granted free of charge',
        ],

        'filters' => [
            'from' => 'From',
            'until' => 'Until',
        ],

        'actions' => [
            'export' => 'Export',
        ],

        'notifications' => [
            'export_completed' => 'Your ledger export has completed and :count row(s) exported.|Your ledger export has completed and :count rows exported.',
        ],
    ],

    'broadcast' => [
        'title' => 'Owner Broadcast',

        'fields' => [
            'target_type' => 'Target by',
            'target' => 'Target',
            'title' => 'Message title',
            'body' => 'Message body',
        ],

        'target_types' => [
            'country' => 'Country',
            'resort' => 'Resort',
            'package' => 'Placement package',
        ],

        'actions' => [
            'send' => 'Send broadcast',
        ],

        'confirmation' => 'This will notify :count owner(s). Every one of them will receive this message.',
        'confirm_label' => 'Notify :count owner(s)',
        'sent' => 'Broadcast sent to :count owner(s).',
        'rate_limited' => 'Daily broadcast limit reached',
        'quota_remaining' => ':count broadcast(s) remaining today.',
    ],

    'commerce_reports' => [
        'title' => 'Commerce Reports',
        'active_placements' => 'Active placements',
        'ending_30' => 'Ending within 30 days',
        'ending_14' => 'Ending within 14 days',
        'ending_7' => 'Ending within 7 days',
        'ending_3' => 'Ending within 3 days',
        'expired_placements' => 'Expired placements',
        'no_term_set' => 'Objects with no term set',
        'free_placements' => 'Free placements',
        'paid_bump_count' => 'Paid bumps',
        'active_campaigns' => 'Active advertising campaigns',
    ],

    'analytics_report' => [
        'title' => 'Analytics Report',

        'filters' => [
            'territory' => 'City',
            'category' => 'Category',
            'language' => 'Language',
            'banner' => 'Banner',
            'object' => 'Object ID',
        ],

        'columns' => [
            'date' => 'Date',
            'kind' => 'Kind',
            'subject_type' => 'Subject',
            'subject_id' => 'Subject ID',
            'territory' => 'City',
            'language' => 'Language',
            'count' => 'Count',
        ],

        'kinds' => [
            'object_card_view' => 'Card views',
            'object_page_view' => 'Page views',
            'photo_view' => 'Views with photos',
            'contact_click' => 'Contact clicks',
            'banner_impression' => 'Banner impressions',
            'banner_click' => 'Banner clicks',
        ],

        'actions' => [
            'export' => 'Export',
        ],

        'notifications' => [
            'export_completed' => '{1} :count row exported.|[2,*] :count rows exported.',
        ],

        'derived' => [
            'most_viewed_objects' => 'Most Viewed Objects',
            'most_popular_categories' => 'Most Popular Categories',
            'banner_click_through_rate' => 'Banner Click-Through Rate',
            'new_owner_count' => 'New Owners',
            'new_object_count' => 'New Objects',
            'bump_count' => 'Bumps',
            'published_promotion_count' => 'Published Promotions',
            'pending_moderation_count' => 'Pending Moderation',
            'empty_state' => 'No data for this period.',
        ],

        'traffic_source' => [
            'title' => 'Traffic Sources',
            'channels' => [
                'direct' => 'Direct',
                'search' => 'Search',
                'social' => 'Social',
                'referral' => 'Referral',
                'internal' => 'Internal (portal)',
                'campaign' => 'Campaign',
            ],
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

    'api_clients' => [
        'title' => 'API clients',
        'model_label' => 'API client',

        'form' => [
            'name' => 'Name',
            'contact' => 'Contact',
            'active' => 'Active',
        ],

        'columns' => [
            'tokens' => 'Tokens',
            'created_by' => 'Created by',
            'created_at' => 'Created',
        ],

        'tokens' => [
            'title' => 'Tokens',
            'name' => 'Name',
            'resources' => 'Resources',
            'countries' => 'Countries',
            'categories' => 'Categories',
            'rate_limit' => 'Rate limit (req/min)',
            'rate_limit_default' => 'Portal default',
            'rate_limit_hint' => 'Leave empty to use the portal-wide default.',
            'expires_at' => 'Expires',
            'last_used_at' => 'Last used',
            'never_used' => 'Never used',
            'revoked' => 'Active',
            'unrestricted' => 'Unrestricted',
            'countries_hint' => 'Leave empty for no country restriction.',
            'categories_hint' => 'Leave empty for no category restriction.',
            'issue' => 'Issue token',
            'issued' => 'Token issued',
            'issued_body' => 'This value is shown once and cannot be recovered afterward: :token',
            'edit_scope' => 'Edit scope',
            'revoke' => 'Revoke',
            'revoke_confirm' => 'The token stops working on its very next request. This cannot be undone.',
            'applied' => 'Change applied.',
            'resource' => [
                'countries' => 'Countries',
                'territories' => 'Territories',
                'object_types' => 'Object types',
                'amenities' => 'Amenities',
                'objects' => 'Objects',
                'object_reviews' => 'Object reviews',
                'news' => 'News',
                'promotions' => 'Promotions',
                'articles' => 'Articles',
            ],
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
            'analytics' => 'Analytics',
            'seo' => 'SEO',
            'api' => 'API',
        ],

        'fields' => [
            'portal.name' => 'Portal name',
            'portal.logo_path' => 'Logo path',
            'portal.contact_email' => 'Contact email',
            'portal.contact_phone' => 'Contact phone',
            'portal.social_instagram_url' => 'Instagram URL',
            'portal.social_telegram_url' => 'Telegram URL',
            'portal.social_facebook_url' => 'Facebook URL',

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
            'notifications.dispatch_max_retries' => 'Maximum retry sweeps for a failed dispatch',
            'notifications.broadcast_rate_limit' => 'Maximum administrator broadcasts per day',

            'integrations.map_tile_provider' => 'Map tile provider',
            'integrations.map_tile_key' => 'Map tile API key',
            'integrations.captcha_provider' => 'CAPTCHA provider',
            'integrations.captcha_site_key' => 'CAPTCHA site key',
            'integrations.captcha_secret' => 'CAPTCHA secret',
            'integrations.analytics_measurement_id' => 'Analytics measurement ID',

            'security.session_lifetime_minutes' => 'Session lifetime (minutes)',
            'security.sign_in_max_attempts' => 'Sign-in attempts before lockout',

            'journal.retention_days' => 'Journal retention (days)',

            'analytics.raw_retention_days' => 'Raw event retention (days)',

            'seo.default_og_image' => 'Default Open Graph image',
            'seo.robots_extra' => 'Extra robots.txt directives',
        ],
    ],

    'backup_administration' => [
        'navigation_label' => 'Backups',
        'title' => 'Backup Administration',
        'none' => 'None yet',
        'staleness_warning' => 'The last database backup is older than :hours hours.',
        'destination_unreachable' => 'The backup destination could not be reached. The figures below may be incomplete — this has been reported.',

        'fields' => [
            'last_database_backup' => 'Last successful database backup',
            'last_media_backup' => 'Last successful media backup',
        ],

        'actions' => [
            'run_now' => 'Run backup now',
            'download_report' => 'Download technical report',
        ],

        'notifications' => [
            'queued' => 'Backup queued — it will run in the background.',
        ],

        'log' => [
            'database_title' => 'Database backup log',
            'media_title' => 'Media backup log',

            'columns' => [
                'date' => 'Date',
                'size' => 'Size',
            ],
        ],
    ],

    'backup_restore' => [
        'navigation_label' => 'Restore',
        'title' => 'Restore Backup',
        'none' => 'No database backups exist yet.',
        'warning' => 'Restoring replaces the entire database with the selected backup. Every object, owner, placement, and record written after that backup was taken is permanently lost. This cannot be undone.',

        'columns' => [
            'date' => 'Date',
            'size' => 'Size',
        ],

        'steps' => [
            'select' => [
                'title' => '1. Select a backup',
            ],
            'confirm' => [
                'title' => '2. Confirm',
                'description' => 'This restores the database to its exact state as of :timestamp. Everything written since then will be permanently lost.',
                'confirmed' => 'Confirmed. Re-authenticate below to proceed.',
            ],
        ],

        'actions' => [
            'select' => 'Select',
            'selected' => 'Selected',
            'confirm' => 'I understand, continue',
            'restore' => '3. Re-authenticate and restore',
            'code_label' => 'Current authenticator code',
            'code_invalid' => 'That code is not currently valid. Enter the current code from your authenticator app.',
        ],

        'notifications' => [
            'queued' => 'Restore queued — it will run in the background.',
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

    'translation_report' => [
        'title' => 'Translation report',

        'columns' => [
            'entity' => 'Entity',
            'state' => 'State',
            'needs_review_count' => ':count needs review',
        ],

        'state' => [
            'missing' => 'Missing',
            'needs_review' => 'Needs review',
        ],

        'actions' => [
            'copy_from_primary' => 'Copy from primary',
            'publish' => 'Publish',
        ],

        'notifications' => [
            'copied' => 'Copied from the primary language — marked as needing review.',
            'published' => 'Translation published.',
            'publish_refused' => 'No translation exists yet for this language.',
        ],
    ],

    'reviews' => [
        'title' => 'Reviews',
        'model_label' => 'review',
        'anonymous' => 'Guest',

        'columns' => [
            'object' => 'Object',
            'author' => 'Author',
            'rating' => 'Rating',
            'status' => 'Status',
            'reported' => 'Reported',
            'owner_reply' => 'Owner reply',
            'submitted_at' => 'Submitted',
        ],

        'status' => [
            'pending' => 'Pending',
            'published' => 'Published',
            'rejected' => 'Rejected',
        ],

        'filters' => [
            'reported' => 'Reported only',
        ],

        'actions' => [
            'publish' => 'Publish',
            'reject' => 'Reject',
            'hide' => 'Hide',
            'reason' => 'Reason',
        ],

        'notifications' => [
            'published' => 'Review published.',
            'rejected' => 'Review rejected.',
            'hidden' => 'Review hidden.',
        ],
    ],

    'moderation_queue' => [
        'title' => 'Moderation queue',
        'model_label' => 'moderation request',

        'columns' => [
            'submitted_at' => 'Submitted',
            'owner' => 'Owner',
            'object' => 'Object',
            'section' => 'Section',
            'change_summary' => 'Changed fields',
            'status' => 'Status',
            'assigned_to' => 'Assigned to',
            'country' => 'Country',
        ],

        'filters' => [
            'submitted_between' => 'Submitted between',
            'from' => 'From',
            'until' => 'Until',
        ],

        'actions' => [
            'review' => 'Review',
            'reassign' => 'Reassign',
            'reassign_to' => 'Reassign to',
        ],

        'notifications' => [
            'reassigned' => 'Request reassigned.',
        ],
    ],

    'moderation_review' => [
        'title' => 'Review change request',

        'columns' => [
            'field' => 'Field',
            'published' => 'Published',
            'proposed' => 'Proposed',
        ],

        'fields' => [
            'reason' => 'Reason',
            'comment' => 'Comment',
            'accepted_fields' => 'Fields to accept',
        ],

        'actions' => [
            'approve' => 'Approve',
            'reject' => 'Reject',
            'request_revision' => 'Request revision',
            'partially_accept' => 'Partially accept',
        ],

        'notifications' => [
            'approved' => 'Change approved and published.',
            'rejected' => 'Change rejected.',
            'revision_requested' => 'Revision requested from the owner.',
            'partially_accepted' => 'Accepted fields applied; the rest returned for revision.',
            'partial_refused' => 'Partial acceptance could not be applied.',
        ],
    ],

    'action_journal' => [
        'title' => 'Action journal',
        'model_label' => 'journal entry',

        'columns' => [
            'occurred_at' => 'Occurred at',
            'actor' => 'Actor',
            'action' => 'Action',
            'target' => 'Target',
            'target_id' => 'Target ID',
            'previous_value' => 'Previous value',
            'new_value' => 'New value',
            'ip_address' => 'IP address',
            'device' => 'Device',
            'outcome' => 'Outcome',
        ],

        'outcome' => [
            'success' => 'Success',
            'failure' => 'Failure',
        ],

        'filters' => [
            'occurred_between' => 'Occurred between',
        ],

        'actions' => [
            'export' => 'Export',
        ],

        'notifications' => [
            'export_completed' => '{0}The export finished with no rows.|{1}The export finished with :count row.|[2,*]The export finished with :count rows.',
        ],
    ],

    'data_transfer' => [
        'kinds' => [
            'objects' => 'Objects',
            'owners' => 'Owners',
            'contacts' => 'Contact channels',
            'prices' => 'Prices',
            'services' => 'Services',
            'geography' => 'Geographic reference data',
            'packages' => 'Placement packages',
            'payments' => 'Payments',
            'banners' => 'Banners',
            'news' => 'News',
            'promotions' => 'Promotions',
            'statistics' => 'Statistics',
            'action_journal' => 'Action journal',
        ],

        'columns' => [
            'objects' => [
                'id' => 'ID',
                'ulid' => 'ULID',
                'owner_id' => 'Owner ID',
                'object_type_id' => 'Object type ID',
                'territory_id' => 'Territory ID',
                'country_id' => 'Country ID',
                'address' => 'Address',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'status' => 'Status',
                'availability_status' => 'Availability status',
            ],
            'owners' => [
                'id' => 'ID',
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'company' => 'Company',
                'country_id' => 'Country ID',
                'locale' => 'Preferred language',
                'blocked_at' => 'Blocked at',
            ],
            'contacts' => [
                'id' => 'ID',
                'object_id' => 'Object ID',
                'contact_channel_type_id' => 'Channel type ID',
                'raw_value' => 'Value',
                'derived_link' => 'Resolved link',
                'label' => 'Label',
                'display_order' => 'Display order',
                'is_active' => 'Active',
            ],
            'prices' => [
                'id' => 'ID',
                'priceable_type' => 'Priced entity type',
                'priceable_id' => 'Priced entity ID',
                'type' => 'Rate type',
                'calculation_unit' => 'Calculation unit',
                'amount' => 'Amount',
                'currency' => 'Currency',
                'valid_from' => 'Valid from',
                'valid_until' => 'Valid until',
            ],
            'services' => [
                'id' => 'ID',
                'amenity_group_id' => 'Group ID',
                'icon_path' => 'Icon',
                'is_filterable' => 'Filterable',
                'is_active' => 'Active',
                'display_order' => 'Display order',
            ],
            'geography' => [
                'id' => 'ID',
                'parent_id' => 'Parent territory ID',
                'country_id' => 'Country ID',
                'level_id' => 'Level ID',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'is_active' => 'Active',
                'display_order' => 'Display order',
            ],
            'packages' => [
                'id' => 'ID',
                'placement_tier_id' => 'Tier ID',
                'object_type_id' => 'Object type ID',
                'price' => 'Price',
                'currency' => 'Currency',
                'validity_days' => 'Validity (days)',
                'bump_allowed' => 'Bump allowed',
                'bump_interval_hours' => 'Bump interval (hours)',
                'free_bumps_per_period' => 'Free bumps per period',
                'paid_bump_price' => 'Paid bump price',
                'is_active' => 'Active',
            ],
            'payments' => [
                'id' => 'ID',
                'object_id' => 'Object ID',
                'banner_id' => 'Banner ID',
                'service' => 'Service',
                'placement_package_id' => 'Package ID',
                'amount' => 'Amount',
                'currency' => 'Currency',
                'paid_at' => 'Paid at',
                'valid_from' => 'Valid from',
                'valid_until' => 'Valid until',
                'payment_method' => 'Payment method',
                'document_number' => 'Document number',
                'status' => 'Status',
                'responsible_staff_id' => 'Responsible staff ID',
            ],
            'banners' => [
                'id' => 'ID',
                'banner_slot_id' => 'Slot ID',
                'name' => 'Name',
                'advertiser' => 'Advertiser',
                'destination_link' => 'Destination link',
                'starts_at' => 'Starts at',
                'ends_at' => 'Ends at',
                'is_active' => 'Active',
                'impressions' => 'Impressions',
                'clicks' => 'Clicks',
            ],
            'news' => [
                'id' => 'ID',
                'author_id' => 'Author ID',
                'object_id' => 'Object ID',
                'territory_id' => 'Territory ID',
                'article_category_id' => 'Category ID',
                'publish_at' => 'Publish at',
                'end_at' => 'End at',
                'is_pinned' => 'Pinned',
                'status' => 'Status',
                'moderation_status' => 'Moderation status',
                'view_count' => 'View count',
            ],
            'promotions' => [
                'id' => 'ID',
                'object_id' => 'Object ID',
                'territory_id' => 'Territory ID',
                'starts_at' => 'Starts at',
                'ends_at' => 'Ends at',
                'status' => 'Status',
                'moderation_status' => 'Moderation status',
            ],
            'statistics' => [
                'id' => 'ID',
                'date' => 'Date',
                'subject_type' => 'Subject type',
                'subject_id' => 'Subject ID',
                'kind' => 'Kind',
                'contact_channel_type_id' => 'Channel type ID',
                'territory_id' => 'Territory ID',
                'country_id' => 'Country ID',
                'locale' => 'Language',
                'count' => 'Count',
            ],
            'action_journal' => [
                'id' => 'ID',
                'event' => 'Event',
                'auditable_type' => 'Target type',
                'auditable_id' => 'Target ID',
                'user_type' => 'Actor type',
                'user_id' => 'Actor ID',
                'old_values' => 'Previous value',
                'new_values' => 'New value',
                'ip_address' => 'IP address',
                'user_agent' => 'Device',
                'created_at' => 'Occurred at',
            ],
        ],

        'export' => [
            'actions' => [
                'trigger' => 'Export',
                'download_json' => 'Download JSON',
            ],
            'notifications' => [
                'completed' => '{0}No rows were exported.|{1}:count row exported.|[2,*]:count rows exported.',
            ],
        ],

        'import' => [
            'title' => 'Import Data',
            'fields' => [
                'kind' => 'Data type',
                'file' => 'Spreadsheet file (XLSX or CSV)',
            ],
            'mapping_intro' => '{0}The file carried no data rows.|{1}:count row was read from the file. Match each field below to the column that carries it.|[2,*]:count rows were read from the file. Match each field below to the column that carries it.',
            'actions' => [
                'parse' => 'Read file',
                'validate' => 'Validate & preview',
                'confirm' => 'Confirm import',
                'start_over' => 'Start over',
                'start_new' => 'Start a new import',
            ],
            'summary' => [
                'total' => 'Total rows',
                'to_create' => 'Would create',
                'to_update' => 'Would update',
                'errors' => 'Rows with errors',
                'duplicates' => 'Possible duplicates',
            ],
            'errors' => [
                'title' => 'Row errors',
                'row' => 'Row :row',
                'unsupported_file' => 'Only XLSX and CSV files are accepted.',
            ],
            'duplicates' => [
                'title' => 'Possible duplicates',
                'intro' => 'These rows would create a new object that looks like one already in the catalog. Nothing is merged automatically — review each pairing before deciding what to do with it.',
                'row' => 'Row :row',
                'signals' => [
                    'name' => 'Similar name (match :score)',
                    'phone' => 'Matching phone number',
                    'website' => 'Matching website',
                    'address' => 'Similar address (match :score)',
                    'coordinates' => 'Coordinates :meters m away',
                ],
            ],
            'report' => [
                'title' => 'Recent imports',
                'file' => 'File',
                'processed' => 'Processed',
                'successful' => 'Successful',
                'failed' => 'Failed',
                'completed_at' => 'Completed',
                'pending' => 'Processing…',
                'view_errors' => 'View errors',
            ],
            'notifications' => [
                'empty_file' => 'The file carried no data rows.',
                'expired' => 'This import session expired — start over.',
                'queued' => '{0}Import queued.|{1}Import queued — :count row will be processed in the background.|[2,*]Import queued — :count rows will be processed in the background.',
                'completed_body' => '{0}No rows were imported.|{1}:count row imported successfully.|[2,*]:count rows imported successfully.',
                'completed_failures' => '{1}:count row failed and was skipped.|[2,*]:count rows failed and were skipped.',
            ],
        ],
    ],

    'article_categories' => [
        'title' => 'Article Categories',
        'model_label' => 'article category',

        'columns' => [
            'active' => 'Active',
        ],

        'form' => [
            'slug' => 'Slug',
            'name' => 'Name',
            'display_order' => 'Display order',
        ],
    ],

    'article_tags' => [
        'title' => 'Article Tags',
        'model_label' => 'article tag',

        'columns' => [
            'active' => 'Active',
        ],

        'form' => [
            'slug' => 'Slug',
            'name' => 'Name',
            'display_order' => 'Display order',
        ],
    ],

    'articles' => [
        'title' => 'Articles',
        'model_label' => 'article',

        'form' => [
            'author' => 'Author',
            'category' => 'Category',
            'status' => 'Status',
            'publish_at' => 'Publish at',
            'cover_image' => 'Cover image',
            'related_objects' => 'Related objects',
            'related_territories' => 'Related territories',
            'tags' => 'Tags',
            'title' => 'Title',
            'summary' => 'Summary',
            'body' => 'Body',
            'slug' => 'Slug',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'seo_canonical_url' => 'Canonical URL override',
            'seo_indexable' => 'Indexable',
            'seo_og_title' => 'Open Graph title',
            'seo_og_description' => 'Open Graph description',
            'seo_og_image' => 'Open Graph image URL',
        ],

        'status' => [
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
        ],

        'lifecycle' => [
            'publish' => 'Publish',
            'schedule' => 'Schedule',
            'archive' => 'Archive',
            'restore' => 'Restore',
            'applied' => 'Change applied.',
            'schedule_refused' => 'This article cannot be scheduled',
        ],
    ],

    'news_items' => [
        'title' => 'News',
        'model_label' => 'news item',

        'columns' => [
            'view_count' => 'Views',
        ],

        'form' => [
            'author' => 'Author',
            'object' => 'Object',
            'any_object' => 'Portal-wide (no object)',
            'territory' => 'Territory',
            'category' => 'Category',
            'status' => 'Status',
            'publish_at' => 'Publish at',
            'end_at' => 'End at',
            'pinned' => 'Pinned',
            'cover_image' => 'Cover image',
            'gallery' => 'Gallery',
            'title' => 'Title',
            'summary' => 'Summary',
            'body' => 'Body',
            'slug' => 'Slug',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'seo_canonical_url' => 'Canonical URL override',
            'seo_indexable' => 'Indexable',
            'seo_og_title' => 'Open Graph title',
            'seo_og_description' => 'Open Graph description',
            'seo_og_image' => 'Open Graph image URL',
        ],

        'status' => [
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'withdrawn' => 'Withdrawn',
        ],

        'moderation_status' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'revision_requested' => 'Revision requested',
        ],

        'lifecycle' => [
            'publish' => 'Publish',
            'schedule' => 'Schedule',
            'pin' => 'Pin',
            'unpin' => 'Unpin',
            'withdraw' => 'Withdraw',
            'archive' => 'Archive',
            'restore' => 'Restore',
            'applied' => 'Change applied.',
            'schedule_refused' => 'This news item cannot be scheduled',
        ],
    ],

    'promotions' => [
        'title' => 'Promotions',
        'model_label' => 'promotion',

        'form' => [
            'object' => 'Object',
            'territory' => 'Territory',
            'status' => 'Status',
            'starts_at' => 'Starts at',
            'ends_at' => 'Ends at',
            'image' => 'Image',
            'title' => 'Title',
            'summary' => 'Description',
            'slug' => 'Slug',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'seo_canonical_url' => 'Canonical URL override',
            'seo_indexable' => 'Indexable',
            'seo_og_title' => 'Open Graph title',
            'seo_og_description' => 'Open Graph description',
            'seo_og_image' => 'Open Graph image URL',
        ],

        'status' => [
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'archived' => 'Archived',
        ],

        'moderation_status' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'revision_requested' => 'Revision requested',
        ],

        'lifecycle' => [
            'publish' => 'Publish',
            'schedule' => 'Schedule',
            'archive' => 'Archive',
            'delete' => 'Delete',
            'restore' => 'Restore',
            'applied' => 'Change applied.',
            'schedule_refused' => 'This promotion cannot be scheduled',
        ],
    ],

    'cabinet' => [
        'dashboard' => [
            'title' => 'Dashboard',
            'object_name' => 'Object',
            'active_package' => 'Active package',
            'no_active_package' => 'No active package',
            'current_tier' => 'Current tier',
            'expires_at' => 'Placement expires',
            'catalog_position' => 'Catalog position',
            'catalog_position_unavailable' => 'Not yet available',
            'views_today' => 'Views today',
            'views_this_week' => 'Views this week',
            'views_this_month' => 'Views this month',
            'views_all_time' => 'Views all-time',
            'messenger_clicks' => 'Messenger clicks',
            'website_clicks' => 'Website clicks',
            'expiry_warning_today' => 'Your placement package expires today.',
            'expiry_warning_days' => 'Your placement package expires in :days day(s).',
            'staleness_notice' => 'Your listing information may be out of date. Review and update it to keep it accurate for visitors.',
            'quick_actions_section' => 'Quick actions',
            'quick_actions' => [
                'edit_object' => 'Edit object',
                'bump_object' => 'Bump object',
                'add_photos' => 'Add photos',
                'add_news' => 'Add news',
                'add_promotion' => 'Add promotion',
                'not_yet_available' => 'This screen is not available yet.',
            ],
        ],

        'statistics' => [
            'title' => 'Statistics',
            'object_name' => 'Object',
            'page_views' => 'Page views',
            'photo_views' => 'Views with photos',
            'contact_clicks_total' => 'Contact clicks (total)',
            'favorite_count' => 'Favorites',
            'channel_breakdown_title' => 'Contact clicks by channel',
            'channel_breakdown_empty' => 'No contact clicks recorded yet.',
            'traffic_source_title' => 'Traffic sources',
            'traffic_source_empty' => 'No traffic-source data recorded yet.',
            'traffic_source_campaign' => 'Campaign: :campaign',
            'traffic_channels' => [
                'direct' => 'Direct',
                'search' => 'Search',
                'social' => 'Social',
                'referral' => 'Referral',
                'internal' => 'Internal (portal)',
                'campaign' => 'Campaign',
            ],
        ],

        'availability' => [
            'mark_available' => 'Mark as available',
            'mark_unavailable' => 'Mark as unavailable',
            'toggled' => 'Availability updated.',
        ],

        'bump' => [
            'title' => 'Bump object',
            'description' => 'Move your object to the top of its tier in its own territory. This uses one of the free bumps your placement package allows — bumping is the only cabinet action that depends on your current package.',
            'confirm' => 'Bump now',
            'applied' => 'Your object has been bumped.',
            'refused' => 'This object could not be bumped',
            'refused_reasons' => [
                'not_allowed_by_package' => 'Your current placement package does not allow bumping.',
                'interval_not_elapsed' => 'You can bump again after the :hours-hour interval since your last free bump has passed.',
                'allowance_exhausted' => 'You have used all :count free bump(s) included in your current placement.',
                'no_current_placement' => 'This object has no active placement to bump.',
                'unknown' => 'This object could not be bumped right now.',
            ],
        ],

        'settings' => [
            'title' => 'Settings',
            'locale' => 'Interface language',
            'locale_placeholder' => 'Portal default',
            'notification_preferences_title' => 'Notification preferences',
            'notification_preferences_description' => 'Choose which optional notifications you want to receive. Placement, moderation, and system notices always reach you regardless of these settings.',
            'notification_types' => [
                'information_out_of_date' => 'Your listing information may be out of date',
                'confirm_availability_status' => 'Reminders to confirm your availability status',
                'administration_message' => 'Messages from the portal administration',
            ],
        ],

        'notifications' => [
            'title' => 'Notifications',
            'model_label' => 'notification',
            'empty' => 'No notifications yet.',

            'columns' => [
                'title' => 'Title',
                'body' => 'Message',
                'received_at' => 'Received',
                'status' => 'Status',
            ],

            'status' => [
                'unread' => 'Unread',
                'read' => 'Read',
            ],

            'actions' => [
                'mark_read' => 'Mark as read',
                'mark_unread' => 'Mark as unread',
            ],
        ],

        'objects' => [
            'title' => 'Edit object',
            'model_label' => 'object',

            'sections' => [
                'core' => 'Core',
                'geography' => 'Geography',
                'contacts' => 'Contacts',
                'translations' => 'Translations',
                'seo' => 'SEO',
            ],

            'form' => [
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
            ],

            'lifecycle' => [
                'saved' => 'Saved.',
                'submitted_for_review' => 'Submitted for review. Your changes will go live once a moderator approves them.',
            ],

            'moderation_feedback' => [
                'title' => 'Moderation feedback',
                'pending' => 'Your latest change is awaiting review.',
                'rejected_with_reason' => 'Your latest change was rejected. Reason: :reason',
                'revision_requested_with_reason' => 'A moderator requested changes to your latest submission. Reason: :reason',
            ],

            'eligibility' => [
                'title' => 'Before this object can be published',
                'coordinates' => 'Coordinates (latitude and longitude) are required.',
                'name' => 'A name in the primary language is required.',
            ],

            'staleness' => [
                'title' => 'Information may be out of date',
                'notice' => 'It has been a while since this listing was last updated. Review the details below and save any changes to keep it accurate for visitors.',
            ],
        ],

        'photos' => [
            'title' => 'Photos',
            'model_label' => 'photo',
            'empty' => 'No photos uploaded yet.',

            'columns' => [
                'photo' => 'Photo',
                'caption' => 'Caption',
                'primary' => 'Primary',
            ],

            'actions' => [
                'upload' => 'Upload',
                'set_primary' => 'Set as primary',
                'edit_caption' => 'Edit caption',
                'delete' => 'Delete',
            ],

            'upload_form' => [
                'files' => 'Photos',
            ],

            'caption_form' => [
                'caption' => 'Caption',
            ],

            'notifications' => [
                'uploaded' => 'Photos uploaded.',
                'limit_reached' => 'Photo limit reached',
                'limit_reached_body' => 'This object already holds the maximum of :max photo(s). Delete one before uploading more.',
                'primary_set' => 'Primary photo updated.',
                'caption_saved' => 'Caption saved.',
                'deleted' => 'Photo deleted.',
            ],
        ],

        'rooms' => [
            'title' => 'Rooms',
            'model_label' => 'room',

            'sections' => [
                'core' => 'Room details',
                'translations' => 'Translations',
                'amenities' => 'Amenities',
                'prices' => 'Prices',
            ],

            'form' => [
                'capacity' => 'Capacity',
                'room_count' => 'Number of rooms',
                'area_sqm' => 'Area (m²)',
                'bed_configuration' => 'Bed configuration',
                'max_guests' => 'Maximum guests',
                'display_order' => 'Display order',
                'has_extra_bed' => 'Extra bed available',
                'is_active' => 'Active',
                'name' => 'Name',
                'description' => 'Description',
            ],

            'columns' => [
                'name' => 'Name',
                'capacity' => 'Capacity',
                'max_guests' => 'Max. guests',
                'area_sqm' => 'Area (m²)',
                'is_active' => 'Active',
                'display_order' => 'Order',
            ],

            'price_form' => [
                'type' => 'Rate label',
                'calculation_unit' => 'Calculation unit',
                'amount' => 'Amount',
                'currency' => 'Currency',
                'valid_from' => 'Valid from',
                'valid_until' => 'Valid until',
                'comment' => 'Comment',
                'add' => 'Add price',
            ],

            'calculation_unit' => [
                'per_room' => 'Per room',
                'per_person' => 'Per person',
                'per_night' => 'Per night',
                'per_service' => 'Per service',
                'from' => 'From',
            ],
        ],

        'services' => [
            'title' => 'Services',
            'model_label' => 'service',
            'empty' => 'This object type has no applicable service groups yet.',

            'columns' => [
                'name' => 'Name',
                'selected_count' => 'Selected services',
            ],
        ],

        'news_items' => [
            'title' => 'News',
            'model_label' => 'news item',

            'form' => [
                'title' => 'Title',
                'summary' => 'Summary',
                'body' => 'Body',
                'image' => 'Image',
                'publish_at' => 'Publication date',
            ],

            'columns' => [
                'title' => 'Title',
                'status' => 'Status',
                'publish_at' => 'Publication date',
            ],

            'status' => [
                'draft' => 'Draft',
                'scheduled' => 'Scheduled',
                'published' => 'Published',
                'withdrawn' => 'Withdrawn',
            ],

            'lifecycle' => [
                'published' => 'Published.',
                'submitted_for_review' => 'Submitted for review. This item will go live once a moderator approves it.',
            ],
        ],

        'promotions' => [
            'title' => 'Promotions',
            'model_label' => 'promotion',

            'form' => [
                'title' => 'Title',
                'description' => 'Description',
                'image' => 'Image',
                'starts_at' => 'Start date',
                'ends_at' => 'End date',
            ],

            'columns' => [
                'title' => 'Title',
                'status' => 'Status',
                'starts_at' => 'Start date',
                'ends_at' => 'End date',
            ],

            'status' => [
                'draft' => 'Draft',
                'scheduled' => 'Scheduled',
                'published' => 'Published',
                'archived' => 'Archived',
            ],

            'lifecycle' => [
                'published' => 'Published.',
                'submitted_for_review' => 'Submitted for review. This promotion will go live once a moderator approves it.',
            ],
        ],

        'reviews' => [
            'title' => 'Reviews',
            'model_label' => 'review',
            'empty' => 'No reviews yet.',
            'author_fallback' => 'Guest',

            'columns' => [
                'rating' => 'Rating',
                'author' => 'Reviewer',
                'body' => 'Review',
                'status' => 'Status',
                'owner_reply' => 'Your reply',
                'reported' => 'Reported',
                'created_at' => 'Submitted',
            ],

            'status' => [
                'pending' => 'Pending',
                'published' => 'Published',
                'rejected' => 'Rejected',
            ],

            'actions' => [
                'reply' => 'Reply',
                'report' => 'Report',
            ],

            'reply_form' => [
                'owner_reply' => 'Your reply',
            ],

            'report_form' => [
                'report_reason' => 'Reason',
            ],

            'notifications' => [
                'replied' => 'Reply posted.',
                'reported' => 'Review reported.',
            ],
        ],
    ],

];
