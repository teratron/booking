<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | URLs per sitemap file
    |--------------------------------------------------------------------------
    |
    | The sitemap protocol's own ceiling is 50,000 URLs or 50MB per file,
    | whichever is reached first. This stays comfortably under that so a
    | single file never approaches the size limit.
    |
    */

    'urls_per_file' => (int) env('SITEMAP_URLS_PER_FILE', 10000),

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Every sitemap artefact is written here by the generation job and read
    | back, unchanged, by the routes that serve them — never computed per
    | request.
    |
    */

    'disk' => env('SITEMAP_DISK', 'public'),

];
