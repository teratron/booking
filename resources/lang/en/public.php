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
    ],

    'legal' => [
        'not_found' => [
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

];
