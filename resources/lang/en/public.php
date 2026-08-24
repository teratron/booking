<?php

declare(strict_types=1);

return [

    'catalog' => [
        'card' => [
            'details' => 'Details',
            'reviews' => ':count review|:count reviews',
            'no_reviews_yet' => 'No reviews yet',
            'views' => ':count view|:count views',
            'price_from' => 'from',
            'availability' => [
                'available' => 'Vacancies available',
                'unavailable' => 'No vacancies',
                'unspecified' => 'Availability not stated',
            ],
        ],
        'title' => 'Catalog',
        'empty' => 'No objects match your filters yet — try widening your search.',
        'results_count' => ':count object found|:count objects found',
        'view' => [
            'grid' => 'Grid',
            'list' => 'List',
        ],
        'filters' => [
            'any' => 'Any',
            'territory' => 'Destination',
            'type' => 'Type',
            'price' => 'Price range',
            'price_min' => 'From',
            'price_max' => 'To',
            'rating_min' => 'Minimum rating',
            'amenities' => 'Amenities',
            'more' => 'More filters',
        ],
    ],

    'territory' => [
        'promotions' => 'Promotions',
        'map' => 'Map',
        'explore' => 'Explore nearby',
        'typed_catalog_empty' => 'No listings of this type are published in :territory yet.',
    ],

    'seo' => [
        'derived_description' => 'Learn more about :name.',
        'catalog_default_title' => 'Catalog',
        'catalog_in_territory' => 'Catalog in :territory',
        'catalog_default_description' => 'Browse hotels, guesthouses, restaurants, and attractions with direct owner contact.',
    ],

    'blog' => [
        'empty' => 'No articles have been published yet — check back soon.',
        'by' => 'By :name',
        'tags_heading' => 'Tags',
        'related_objects_heading' => 'Mentioned objects',
        'related_territories_heading' => 'Mentioned destinations',
    ],

    'news' => [
        'empty' => 'No news has been published yet — check back soon.',
        'pinned' => 'Pinned',
    ],

    'promotions' => [
        'valid' => 'Valid :from – :until',
    ],

    'object' => [
        'rooms_heading' => 'Rooms & prices',
        'prices_heading' => 'Prices',
        'details_heading' => 'Details',
        'services_heading' => 'Services & facilities',
        'location_heading' => 'Location',
        'directions_link' => 'Get directions',
        'room' => [
            'capacity' => 'Capacity',
            'room_count' => 'Rooms available',
            'area' => 'Area',
            'max_guests' => 'Max guests',
            'extra_bed' => 'Extra bed available',
        ],
        'price' => [
            'calculation_unit' => [
                'per_room' => 'per room',
                'per_person' => 'per person',
                'per_night' => 'per night',
                'per_service' => 'per service',
                'from' => 'from',
            ],
        ],
        'attributes' => [
            'yes' => 'Yes',
            'no' => 'No',
        ],
        'reviews' => [
            'heading' => 'Reviews',
            'anonymous' => 'Guest',
            'owner_reply' => 'Owner reply',
            'form' => [
                'heading' => 'Leave a review',
                'name' => 'Your name',
                'rating' => 'Rating',
                'body' => 'Your review',
                'submit' => 'Submit review',
                'thanks' => 'Thank you — your review has been submitted and is awaiting moderation.',
                'contact_first' => 'Contact this listing to unlock the review form.',
                'refused' => 'We could not submit your review. Please try again.',
            ],
        ],
        'nearby_heading' => 'Nearby objects',
        'similar_heading' => 'Similar objects',
    ],

    'home' => [
        'hero' => [
            'title' => 'Find your next stay',
            'subtitle' => 'Hotels, guesthouses, restaurants, and attractions across Moldova, Ukraine, and Georgia.',
            'submit' => 'Search',
        ],
        'categories_heading' => 'Browse by category',
        'recommended_heading' => 'Recommended for you',
        'newest_heading' => 'Newly added',
        'cities_heading' => 'Popular cities',
        'editorial_heading' => 'News & stories',
        'reviews_heading' => 'What visitors are saying',
        'partners_heading' => 'Our partners',
        'informational' => [
            'title' => 'About the portal',
            'body' => 'We publish independently verified listings for hotels, guesthouses, restaurants, and attractions, and put you in direct contact with the owner — no booking fees, no middleman.',
        ],
    ],

    'shell' => [
        'header' => [
            'owner_entry' => 'Sign in / Cabinet',
            'menu' => 'Menu',
            'language' => 'Language',
            'country' => 'Country',
        ],
        'search' => [
            'placeholder' => 'Search destinations, hotels, restaurants…',
        ],
        'map' => [
            'label' => 'Map of matching objects',
            'unavailable' => 'Map unavailable — no map provider is configured yet.',
        ],
        'nav' => [
            'primary' => 'Primary navigation',
            'news' => 'News',
            'promotions' => 'Promotions',
            'blog' => 'Blog',
            'about' => 'About',
            'contacts' => 'Contacts',
        ],
        'breadcrumbs' => [
            'label' => 'Breadcrumb',
            'home' => 'Home',
        ],
        'footer' => [
            'about_heading' => 'About the portal',
            'contacts_heading' => 'Contacts',
            'destinations_heading' => 'Popular destinations',
            'categories_heading' => 'Categories',
            'privacy' => 'Privacy policy',
            'terms' => 'Terms of use',
            'add_object' => 'Add your object',
        ],
        'feedback' => [
            'trigger' => 'Feedback',
            'heading' => 'Send us feedback',
            'close' => 'Close',
            'thanks' => 'Thank you — your message has been received.',
            'name' => 'Name',
            'email' => 'Email',
            'message' => 'Message',
            'submit' => 'Send',
        ],
        'cookie_consent' => [
            'message' => 'We use cookies to keep the portal working smoothly and to understand how it is used.',
            'accept' => 'Accept',
        ],
    ],

    'legal' => [
        'not_found' => [
            'eyebrow' => 'Oops!',
            'title' => 'Page not found',
            'body' => "The page you're looking for doesn't exist, may have been moved, or the address was typed incorrectly.",
            'home_link' => 'Back to home',
        ],
        'privacy' => [
            'title' => 'Privacy Policy',
            'body' => [
                'This portal is a directory: it publishes information about accommodation, dining, and leisure objects and connects visitors directly with the object owner\'s own phone number or messenger. It does not process bookings or payments on a visitor\'s behalf, and this policy explains what information we do collect and how it is used.',
                'Browsing data: like most websites, we record aggregate visit statistics (pages viewed, approximate location, device type) to understand how the portal is used and to improve it. This data is not used to build individual advertising profiles.',
                'Information you provide: if you use the feedback form, we store the name, email address, and message you submit, together with the page you submitted it from, so we can read and respond to it. If you are an object owner, your cabinet account holds the contact and business details you provided when your listing was set up.',
                'We do not sell or rent visitor contact information to third parties. Information is used only to operate and improve the portal and to respond to messages sent through it.',
                'You may ask us to review, correct, or delete personal information we hold about you by writing to the contact address published in the footer of this site.',
                'This policy may be updated from time to time as the portal changes; the version published at this address is always the current one.',
            ],
        ],
        'terms' => [
            'title' => 'Terms of Use',
            'body' => [
                'By browsing this portal you agree to these terms. If you do not agree with them, please do not use the site.',
                'This portal is a directory and advertising platform, not a booking intermediary. It publishes information supplied by object owners and hands visitors directly to the owner\'s own contact channels; it is not a party to any reservation, payment, or agreement made between a visitor and an object owner.',
                'Object owners are responsible for the accuracy of the information, photographs, and prices they publish, and for complying with the laws applicable to their business.',
                'Visitors agree not to misuse the portal — including submitting false information through the feedback or contact forms, or attempting to extract portal data through automated means without permission.',
                'To the extent permitted by law, the portal is not liable for the conduct of object owners, the accuracy of listing information, or the outcome of any arrangement a visitor makes directly with an owner.',
                'These terms may be updated from time to time; the version published at this address is always the current one.',
            ],
        ],
    ],

    'static' => [
        'about' => [
            'title' => 'About',
            'body' => [
                'This portal is a directory for accommodation, dining, and leisure across Moldova, Ukraine, and Georgia — a single place to browse what each destination offers before reaching out directly to the people who run it.',
                'We publish what object owners tell us: descriptions, photos, prices, and availability, organised by destination and category so a visitor can find what they are looking for quickly. Every listing hands you straight to the owner\'s own phone number or messenger — we do not take bookings or payments on anyone\'s behalf.',
                'Placement on the portal is paid by object owners, which is how the directory stays free for every visitor to browse.',
            ],
        ],
        'contacts' => [
            'title' => 'Contacts',
            'body' => [
                'Have a question about the portal, spotted an issue with a listing, or want to talk about placing your own object here? Reach us using the details below, or through the feedback form available on every page.',
            ],
            'phone_label' => 'Phone',
            'email_label' => 'Email',
        ],
    ],

];
