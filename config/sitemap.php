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

    /*
    |--------------------------------------------------------------------------
    | Maximum served age
    |--------------------------------------------------------------------------
    |
    | The route that serves the index treats an artefact older than this
    | (or missing entirely — a fresh deploy that has run no generation job
    | yet) as absent: it dispatches a regeneration and answers 503 with a
    | short Retry-After rather than serving a stale or empty sitemap. The
    | hourly schedule keeps a live portal well inside this window.
    |
    */

    'max_age_hours' => (int) env('SITEMAP_MAX_AGE_HOURS', 6),

];
