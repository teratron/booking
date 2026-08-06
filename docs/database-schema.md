# Database Schema

Generated from the applied schema by `php artisan schema:er-diagram` — never
hand-edited. Regenerate after any migration change:

```
php artisan schema:er-diagram
```

Generated at 2026-08-06 16:34:31 · 99 tables · 176 foreign keys.

```mermaid
erDiagram
    amenities {
        bigint id PK
        bigint amenity_group_id
        string icon_path
        boolean is_filterable
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    amenity_group_object_type {
        bigint amenity_group_id PK
        bigint object_type_id PK
    }
    amenity_group_translations {
        bigint id PK
        bigint amenity_group_id
        string locale
        string name
        timestamp created_at
        timestamp updated_at
    }
    amenity_groups {
        bigint id PK
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    amenity_object {
        bigint amenity_id PK
        bigint object_id PK
    }
    amenity_room {
        bigint amenity_id PK
        bigint room_id PK
    }
    amenity_translations {
        bigint id PK
        bigint amenity_id
        string locale
        string name
        timestamp created_at
        timestamp updated_at
    }
    api_clients {
        bigint id PK
        string name
        string contact
        boolean is_active
        bigint created_by
        timestamp created_at
        timestamp updated_at
    }
    article_categories {
        bigint id PK
        string slug
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    article_category_translations {
        bigint id PK
        bigint article_category_id
        string locale
        string name
        timestamp created_at
        timestamp updated_at
    }
    article_object {
        bigint article_id PK
        bigint object_id PK
    }
    article_tag {
        bigint article_id PK
        bigint article_tag_id PK
    }
    article_tags {
        bigint id PK
        string slug
        string name
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    article_territory {
        bigint article_id PK
        bigint territory_id PK
    }
    article_translations {
        bigint id PK
        bigint article_id
        string locale
        string title
        text summary
        text body
        string seo_title
        string seo_description
        string slug
        timestamp created_at
        timestamp updated_at
    }
    articles {
        bigint id PK
        bigint author_id
        bigint article_category_id
        timestamp publish_at
        string status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    audits {
        bigint id PK
        string user_type
        bigint user_id
        string event
        string auditable_type
        bigint auditable_id
        text old_values
        text new_values
        text url
        inet ip_address
        string user_agent
        string tags
        timestamp created_at
        timestamp updated_at
    }
    availability_histories {
        bigint id PK
        bigint object_id
        string from_status
        string to_status
        timestamp changed_at
        bigint changed_by
        string source
    }
    banner_slot_translations {
        bigint id PK
        bigint banner_slot_id
        string locale
        string name
        timestamp created_at
        timestamp updated_at
    }
    banner_slots {
        bigint id PK
        string key
        jsonb surfaces
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    banner_targets {
        bigint id PK
        bigint banner_id
        string target_type
        bigint target_id
        timestamp created_at
        timestamp updated_at
    }
    banner_translations {
        bigint id PK
        bigint banner_id
        string locale
        string link_text
        timestamp created_at
        timestamp updated_at
    }
    banners {
        bigint id PK
        bigint banner_slot_id
        string name
        string advertiser
        string destination_link
        integer display_order
        date starts_at
        date ends_at
        boolean is_active
        bigint impressions
        bigint clicks
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    booking_settings {
        bigint id PK
        bigint object_id
        boolean enabled_by_owner
        integer response_window_hours
        integer checkout_hold_window_minutes
        text cancellation_policy
        integer advance_booking_horizon_days
        timestamp created_at
        timestamp updated_at
    }
    bump_events {
        bigint id PK
        bigint object_id
        bigint placement_package_id
        string scope_type
        bigint scope_id
        timestamp occurred_at
        string type
        bigint actor_id
        integer previous_position
        integer new_position
        numeric price
        text comment
        timestamp created_at
        timestamp updated_at
    }
    cache {
        string key PK
        text value
        bigint expiration
    }
    cache_locks {
        string key PK
        string owner
        bigint expiration
    }
    contact_channel_type_translations {
        bigint id PK
        bigint contact_channel_type_id
        string locale
        string display_name
        timestamp created_at
        timestamp updated_at
    }
    contact_channel_types {
        bigint id PK
        string key
        string icon_path
        string link_template
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    contact_channels {
        bigint id PK
        bigint object_id
        bigint contact_channel_type_id
        string raw_value
        string derived_link
        string label
        integer display_order
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    countries {
        bigint id PK
        string code
        string flag_path
        string currency
        string phone_code
        bigint primary_language_id
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    country_translations {
        bigint id PK
        bigint country_id
        string locale
        string name
        timestamp created_at
        timestamp updated_at
    }
    failed_jobs {
        bigint id PK
        string uuid
        string connection
        string queue
        text payload
        text exception
        timestamp failed_at
    }
    favorites {
        bigint id PK
        bigint object_id
        bigint user_id
        string browser_token
        timestamp created_at
    }
    financial_records {
        bigint id PK
        bigint object_id
        bigint banner_id
        string service
        bigint placement_package_id
        numeric amount
        string currency
        timestamp paid_at
        date valid_from
        date valid_until
        string payment_method
        string document_number
        text comment
        bigint responsible_staff_id
        string status
        timestamp created_at
        timestamp updated_at
    }
    home_block_selections {
        bigint id PK
        bigint country_id
        string block_key
        string selectable_type
        bigint selectable_id
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    job_batches {
        string id PK
        string name
        integer total_jobs
        integer pending_jobs
        integer failed_jobs
        text failed_job_ids
        text options
        integer cancelled_at
        integer created_at
        integer finished_at
    }
    jobs {
        bigint id PK
        string queue
        text payload
        smallint attempts
        integer reserved_at
        integer available_at
        integer created_at
    }
    languages {
        bigint id PK
        string code
        string short_label
        string icon_path
        string text_direction
        boolean is_active
        boolean is_primary
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    media {
        bigint id PK
        string model_type
        bigint model_id
        uuid uuid
        string collection_name
        string name
        string file_name
        string mime_type
        string disk
        string conversions_disk
        bigint size
        json manipulations
        json custom_properties
        json generated_conversions
        json responsive_images
        integer order_column
        timestamp created_at
        timestamp updated_at
    }
    migrations {
        integer id PK
        string migration
        integer batch
    }
    model_has_permissions {
        bigint permission_id PK
        string model_type PK
        bigint model_id PK
    }
    model_has_roles {
        bigint role_id PK
        string model_type PK
        bigint model_id PK
    }
    moderation_requests {
        bigint id PK
        string target_type
        bigint target_id
        string section
        jsonb previous_data
        jsonb proposed_data
        bigint submitted_by
        timestamp submitted_at
        bigint assigned_moderator_id
        string decision
        timestamp decided_at
        bigint decided_by
        text rejection_reason
        text comment
        timestamp created_at
        timestamp updated_at
    }
    module_conflicts {
        bigint module_id PK
        bigint conflicts_with_module_id PK
    }
    module_dependencies {
        bigint module_id PK
        bigint depends_on_module_id PK
    }
    module_settings {
        bigint id PK
        bigint module_id
        string scope_level
        bigint scope_reference_id
        string state
        bigint set_by
        timestamp set_at
        timestamp created_at
        timestamp updated_at
    }
    module_translations {
        bigint id PK
        bigint module_id
        string locale
        string display_name
        text description
        timestamp created_at
        timestamp updated_at
    }
    modules {
        bigint id PK
        string key
        string default_state
        jsonb scopable_levels
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    news_items {
        bigint id PK
        bigint author_id
        bigint object_id
        bigint territory_id
        bigint article_category_id
        timestamp publish_at
        timestamp end_at
        boolean is_pinned
        string status
        string moderation_status
        bigint view_count
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    news_translations {
        bigint id PK
        bigint news_item_id
        string locale
        string title
        text summary
        text body
        string seo_title
        string seo_description
        string slug
        timestamp created_at
        timestamp updated_at
    }
    notification_channel_translations {
        bigint id PK
        bigint notification_channel_id
        string locale
        string name
        timestamp created_at
        timestamp updated_at
    }
    notification_channels {
        bigint id PK
        string key
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    notification_dispatches {
        bigint id PK
        bigint notification_id
        bigint notification_channel_id
        string status
        timestamp attempted_at
        text failure_reason
        string provider_reference
        timestamp created_at
        timestamp updated_at
    }
    notification_templates {
        bigint id PK
        bigint notification_type_id
        string locale
        bigint notification_channel_id
        string subject
        text body
        timestamp created_at
        timestamp updated_at
    }
    notification_types {
        bigint id PK
        string key
        string class
        jsonb default_channels
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    notifications {
        bigint id PK
        bigint recipient_id
        bigint notification_type_id
        string related_type
        bigint related_id
        string title
        text body
        string locale
        timestamp read_at
        bigint created_by
        timestamp created_at
        timestamp updated_at
    }
    object_placements {
        bigint id PK
        bigint object_id
        bigint placement_package_id
        date starts_at
        date ends_at
        integer pinned_position
        integer internal_priority
        string expiry_action_override
        timestamp created_at
        timestamp updated_at
    }
    object_promotions {
        bigint id PK
        bigint object_id
        bigint promotion_label_id
        date starts_at
        date ends_at
        bigint granted_by
        integer weight
        timestamp created_at
        timestamp updated_at
    }
    object_translations {
        bigint id PK
        bigint object_id
        string locale
        string name
        text short_description
        text full_description
        string seo_title
        string seo_description
        string slug
        timestamp created_at
        timestamp updated_at
    }
    object_type_translations {
        bigint id PK
        bigint object_type_id
        string locale
        string name
        string seo_title
        string seo_description
        timestamp created_at
        timestamp updated_at
    }
    object_types {
        bigint id PK
        bigint parent_id
        string key
        string icon_path
        jsonb attribute_schema
        boolean has_rooms
        boolean has_availability_status
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    object_user {
        bigint id PK
        bigint object_id
        bigint user_id
        jsonb permissions
        timestamp created_at
        timestamp updated_at
    }
    objects {
        bigint id PK
        string ulid
        bigint owner_id
        bigint object_type_id
        bigint territory_id
        bigint country_id
        string address
        numeric latitude
        numeric longitude
        USER-DEFINED geom
        jsonb attributes
        string status
        string moderation_status
        string availability_status
        timestamp availability_changed_at
        bigint availability_changed_by
        string availability_previous_status
        timestamp availability_last_confirmed_at
        text availability_comment
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
        bigint deleted_by
    }
    password_reset_tokens {
        string email PK
        string token
        timestamp created_at
    }
    permissions {
        bigint id PK
        string name
        string guard_name
        timestamp created_at
        timestamp updated_at
    }
    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        text name
        string token
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }
    placement_histories {
        bigint id PK
        bigint object_id
        bigint placement_package_id
        date starts_at
        date ends_at
        numeric amount
        string currency
        timestamp paid_at
        string payment_method
        string document_number
        string status
        bigint granted_by
        text comment
        timestamp created_at
        timestamp updated_at
    }
    placement_package_translations {
        bigint id PK
        bigint placement_package_id
        string locale
        string name
        timestamp created_at
        timestamp updated_at
    }
    placement_packages {
        bigint id PK
        bigint placement_tier_id
        bigint object_type_id
        numeric price
        string currency
        integer validity_days
        boolean bump_allowed
        integer bump_interval_hours
        integer free_bumps_per_period
        numeric paid_bump_price
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    placement_tier_translations {
        bigint id PK
        bigint placement_tier_id
        string locale
        string label
        string badge_text
        timestamp created_at
        timestamp updated_at
    }
    placement_tiers {
        bigint id PK
        smallint rank
        string border_colour
        string badge_colour
        string badge_icon
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    prices {
        bigint id PK
        string priceable_type
        bigint priceable_id
        string type
        string calculation_unit
        numeric amount
        string currency
        date valid_from
        date valid_until
        text comment
        timestamp created_at
        timestamp updated_at
    }
    promotion_label_translations {
        bigint id PK
        bigint promotion_label_id
        string locale
        string text
        timestamp created_at
        timestamp updated_at
    }
    promotion_labels {
        bigint id PK
        string border_colour
        string text_colour
        string background_colour
        string icon
        string position_on_card
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    promotion_translations {
        bigint id PK
        bigint promotion_id
        string locale
        string title
        text summary
        text body
        string seo_title
        string seo_description
        string slug
        timestamp created_at
        timestamp updated_at
    }
    promotions {
        bigint id PK
        bigint object_id
        bigint territory_id
        date starts_at
        date ends_at
        string status
        string moderation_status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    redirects {
        bigint id PK
        string locale
        string from_path
        string to_path
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    reservations {
        bigint id PK
        bigint room_id
        bigint guest_id
        date check_in
        date check_out
        integer party_size
        string status
        string payment_reference
        numeric commission_rate
        text reason
        timestamp created_at
        timestamp updated_at
    }
    reviews {
        bigint id PK
        bigint object_id
        smallint rating
        text body
        bigint author_id
        string author_name
        text owner_reply
        timestamp owner_replied_at
        string status
        timestamp reported_at
        bigint reported_by
        text report_reason
        text hidden_reason
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
        bigint deleted_by
    }
    role_has_permissions {
        bigint permission_id PK
        bigint role_id PK
    }
    role_scopes {
        bigint id PK
        bigint user_id
        bigint role_id
        string scope_kind
        bigint scope_reference_id
        bigint granted_by
        timestamp granted_at
        timestamp created_at
        timestamp updated_at
    }
    role_translations {
        bigint id PK
        bigint role_id
        string locale
        string display_name
        timestamp created_at
        timestamp updated_at
    }
    roles {
        bigint id PK
        string name
        string guard_name
        timestamp created_at
        timestamp updated_at
        boolean is_system
    }
    room_availabilities {
        bigint id PK
        bigint room_id
        date date
        string state
        numeric rate_override
        integer minimum_stay
        timestamp created_at
        timestamp updated_at
    }
    room_translations {
        bigint id PK
        bigint room_id
        string locale
        string name
        text description
        timestamp created_at
        timestamp updated_at
    }
    rooms {
        bigint id PK
        bigint object_id
        integer capacity
        integer room_count
        numeric area_sqm
        string bed_configuration
        integer max_guests
        boolean has_extra_bed
        integer display_order
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    sessions {
        string id PK
        bigint user_id
        string ip_address
        text user_agent
        text payload
        integer last_activity
    }
    settings {
        bigint id PK
        string key
        jsonb value
        timestamp created_at
        timestamp updated_at
    }
    spatial_ref_sys {
        integer srid PK
        string auth_name
        integer auth_srid
        string srtext
        string proj4text
    }
    stat_dailies {
        bigint id PK
        date date
        string subject_type
        bigint subject_id
        string kind
        bigint contact_channel_type_id
        bigint territory_id
        bigint country_id
        string locale
        bigint count
        timestamp created_at
        timestamp updated_at
    }
    stat_events {
        bigint id PK
        string kind
        string subject_type
        bigint subject_id
        bigint contact_channel_type_id
        bigint territory_id
        bigint country_id
        string locale
        timestamp occurred_at PK
        string dedup_token
        string source_channel
        string source_domain
        string source_campaign
    }
    stat_events_default {
        bigint id PK
        string kind
        string subject_type
        bigint subject_id
        bigint contact_channel_type_id
        bigint territory_id
        bigint country_id
        string locale
        timestamp occurred_at PK
        string dedup_token
        string source_channel
        string source_domain
        string source_campaign
    }
    territories {
        bigint id PK
        bigint parent_id
        bigint country_id
        bigint level_id
        numeric latitude
        numeric longitude
        USER-DEFINED geom
        string hero_image_path
        boolean is_active
        integer display_order
        timestamp created_at
        timestamp updated_at
    }
    territory_level_translations {
        bigint id PK
        bigint territory_level_id
        string locale
        string singular_name
        string plural_name
        timestamp created_at
        timestamp updated_at
    }
    territory_levels {
        bigint id PK
        bigint country_id
        integer depth_rank
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    territory_translations {
        bigint id PK
        bigint territory_id
        string locale
        string name
        text short_description
        text full_description
        string seo_title
        string seo_description
        string slug
        timestamp created_at
        timestamp updated_at
    }
    two_factor_secrets {
        bigint id PK
        bigint user_id
        text secret
        text recovery_codes
        timestamp confirmed_at
        timestamp created_at
        timestamp updated_at
    }
    users {
        bigint id PK
        string name
        string email
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
    }
    amenity_groups ||--o{ amenities : "amenity_group_id"
    amenity_groups ||--o{ amenity_group_object_type : "amenity_group_id"
    object_types ||--o{ amenity_group_object_type : "object_type_id"
    amenity_groups ||--o{ amenity_group_translations : "amenity_group_id"
    languages ||--o{ amenity_group_translations : "locale"
    amenities ||--o{ amenity_object : "amenity_id"
    objects ||--o{ amenity_object : "object_id"
    amenities ||--o{ amenity_room : "amenity_id"
    rooms ||--o{ amenity_room : "room_id"
    amenities ||--o{ amenity_translations : "amenity_id"
    languages ||--o{ amenity_translations : "locale"
    users ||--o{ api_clients : "created_by"
    article_categories ||--o{ article_category_translations : "article_category_id"
    languages ||--o{ article_category_translations : "locale"
    articles ||--o{ article_object : "article_id"
    objects ||--o{ article_object : "object_id"
    articles ||--o{ article_tag : "article_id"
    article_tags ||--o{ article_tag : "article_tag_id"
    articles ||--o{ article_territory : "article_id"
    territories ||--o{ article_territory : "territory_id"
    articles ||--o{ article_translations : "article_id"
    languages ||--o{ article_translations : "locale"
    article_categories ||--o{ articles : "article_category_id"
    users ||--o{ articles : "author_id"
    users ||--o{ availability_histories : "changed_by"
    objects ||--o{ availability_histories : "object_id"
    banner_slots ||--o{ banner_slot_translations : "banner_slot_id"
    languages ||--o{ banner_slot_translations : "locale"
    banners ||--o{ banner_targets : "banner_id"
    banners ||--o{ banner_translations : "banner_id"
    languages ||--o{ banner_translations : "locale"
    banner_slots ||--o{ banners : "banner_slot_id"
    objects ||--o{ booking_settings : "object_id"
    users ||--o{ bump_events : "actor_id"
    objects ||--o{ bump_events : "object_id"
    placement_packages ||--o{ bump_events : "placement_package_id"
    contact_channel_types ||--o{ contact_channel_type_translations : "contact_channel_type_id"
    languages ||--o{ contact_channel_type_translations : "locale"
    contact_channel_types ||--o{ contact_channels : "contact_channel_type_id"
    objects ||--o{ contact_channels : "object_id"
    languages ||--o{ countries : "primary_language_id"
    countries ||--o{ country_translations : "country_id"
    languages ||--o{ country_translations : "locale"
    objects ||--o{ favorites : "object_id"
    users ||--o{ favorites : "user_id"
    banners ||--o{ financial_records : "banner_id"
    objects ||--o{ financial_records : "object_id"
    placement_packages ||--o{ financial_records : "placement_package_id"
    users ||--o{ financial_records : "responsible_staff_id"
    countries ||--o{ home_block_selections : "country_id"
    permissions ||--o{ model_has_permissions : "permission_id"
    roles ||--o{ model_has_roles : "role_id"
    users ||--o{ moderation_requests : "assigned_moderator_id"
    users ||--o{ moderation_requests : "decided_by"
    users ||--o{ moderation_requests : "submitted_by"
    modules ||--o{ module_conflicts : "conflicts_with_module_id"
    modules ||--o{ module_conflicts : "module_id"
    modules ||--o{ module_dependencies : "depends_on_module_id"
    modules ||--o{ module_dependencies : "module_id"
    modules ||--o{ module_settings : "module_id"
    users ||--o{ module_settings : "set_by"
    languages ||--o{ module_translations : "locale"
    modules ||--o{ module_translations : "module_id"
    article_categories ||--o{ news_items : "article_category_id"
    users ||--o{ news_items : "author_id"
    objects ||--o{ news_items : "object_id"
    territories ||--o{ news_items : "territory_id"
    languages ||--o{ news_translations : "locale"
    news_items ||--o{ news_translations : "news_item_id"
    languages ||--o{ notification_channel_translations : "locale"
    notification_channels ||--o{ notification_channel_translations : "notification_channel_id"
    notification_channels ||--o{ notification_dispatches : "notification_channel_id"
    notifications ||--o{ notification_dispatches : "notification_id"
    languages ||--o{ notification_templates : "locale"
    notification_channels ||--o{ notification_templates : "notification_channel_id"
    notification_types ||--o{ notification_templates : "notification_type_id"
    users ||--o{ notifications : "created_by"
    languages ||--o{ notifications : "locale"
    notification_types ||--o{ notifications : "notification_type_id"
    users ||--o{ notifications : "recipient_id"
    objects ||--o{ object_placements : "object_id"
    placement_packages ||--o{ object_placements : "placement_package_id"
    users ||--o{ object_promotions : "granted_by"
    objects ||--o{ object_promotions : "object_id"
    promotion_labels ||--o{ object_promotions : "promotion_label_id"
    languages ||--o{ object_translations : "locale"
    objects ||--o{ object_translations : "object_id"
    languages ||--o{ object_type_translations : "locale"
    object_types ||--o{ object_type_translations : "object_type_id"
    object_types ||--o{ object_types : "parent_id"
    objects ||--o{ object_user : "object_id"
    users ||--o{ object_user : "user_id"
    users ||--o{ objects : "availability_changed_by"
    countries ||--o{ objects : "country_id"
    users ||--o{ objects : "deleted_by"
    object_types ||--o{ objects : "object_type_id"
    users ||--o{ objects : "owner_id"
    territories ||--o{ objects : "territory_id"
    users ||--o{ placement_histories : "granted_by"
    objects ||--o{ placement_histories : "object_id"
    placement_packages ||--o{ placement_histories : "placement_package_id"
    languages ||--o{ placement_package_translations : "locale"
    placement_packages ||--o{ placement_package_translations : "placement_package_id"
    object_types ||--o{ placement_packages : "object_type_id"
    placement_tiers ||--o{ placement_packages : "placement_tier_id"
    languages ||--o{ placement_tier_translations : "locale"
    placement_tiers ||--o{ placement_tier_translations : "placement_tier_id"
    languages ||--o{ promotion_label_translations : "locale"
    promotion_labels ||--o{ promotion_label_translations : "promotion_label_id"
    languages ||--o{ promotion_translations : "locale"
    promotions ||--o{ promotion_translations : "promotion_id"
    objects ||--o{ promotions : "object_id"
    territories ||--o{ promotions : "territory_id"
    languages ||--o{ redirects : "locale"
    users ||--o{ reservations : "guest_id"
    rooms ||--o{ reservations : "room_id"
    users ||--o{ reviews : "author_id"
    users ||--o{ reviews : "deleted_by"
    objects ||--o{ reviews : "object_id"
    users ||--o{ reviews : "reported_by"
    permissions ||--o{ role_has_permissions : "permission_id"
    roles ||--o{ role_has_permissions : "role_id"
    users ||--o{ role_scopes : "granted_by"
    roles ||--o{ role_scopes : "role_id"
    users ||--o{ role_scopes : "user_id"
    languages ||--o{ role_translations : "locale"
    roles ||--o{ role_translations : "role_id"
    rooms ||--o{ room_availabilities : "room_id"
    languages ||--o{ room_translations : "locale"
    rooms ||--o{ room_translations : "room_id"
    objects ||--o{ rooms : "object_id"
    contact_channel_types ||--o{ stat_dailies : "contact_channel_type_id"
    countries ||--o{ stat_dailies : "country_id"
    languages ||--o{ stat_dailies : "locale"
    territories ||--o{ stat_dailies : "territory_id"
    contact_channel_types ||--o{ stat_events : "contact_channel_type_id"
    contact_channel_types ||--o{ stat_events : "contact_channel_type_id"
    contact_channel_types ||--o{ stat_events : "contact_channel_type_id"
    contact_channel_types ||--o{ stat_events : "contact_channel_type_id"
    countries ||--o{ stat_events : "country_id"
    countries ||--o{ stat_events : "country_id"
    countries ||--o{ stat_events : "country_id"
    countries ||--o{ stat_events : "country_id"
    languages ||--o{ stat_events : "locale"
    languages ||--o{ stat_events : "locale"
    languages ||--o{ stat_events : "locale"
    languages ||--o{ stat_events : "locale"
    territories ||--o{ stat_events : "territory_id"
    territories ||--o{ stat_events : "territory_id"
    territories ||--o{ stat_events : "territory_id"
    territories ||--o{ stat_events : "territory_id"
    contact_channel_types ||--o{ stat_events_default : "contact_channel_type_id"
    contact_channel_types ||--o{ stat_events_default : "contact_channel_type_id"
    contact_channel_types ||--o{ stat_events_default : "contact_channel_type_id"
    contact_channel_types ||--o{ stat_events_default : "contact_channel_type_id"
    countries ||--o{ stat_events_default : "country_id"
    countries ||--o{ stat_events_default : "country_id"
    countries ||--o{ stat_events_default : "country_id"
    countries ||--o{ stat_events_default : "country_id"
    languages ||--o{ stat_events_default : "locale"
    languages ||--o{ stat_events_default : "locale"
    languages ||--o{ stat_events_default : "locale"
    languages ||--o{ stat_events_default : "locale"
    territories ||--o{ stat_events_default : "territory_id"
    territories ||--o{ stat_events_default : "territory_id"
    territories ||--o{ stat_events_default : "territory_id"
    territories ||--o{ stat_events_default : "territory_id"
    countries ||--o{ territories : "country_id"
    territory_levels ||--o{ territories : "level_id"
    territories ||--o{ territories : "parent_id"
    languages ||--o{ territory_level_translations : "locale"
    territory_levels ||--o{ territory_level_translations : "territory_level_id"
    countries ||--o{ territory_levels : "country_id"
    languages ||--o{ territory_translations : "locale"
    territories ||--o{ territory_translations : "territory_id"
    users ||--o{ two_factor_secrets : "user_id"
```
