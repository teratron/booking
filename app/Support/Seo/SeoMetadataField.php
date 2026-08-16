<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * The two fields the resolution ladder's template rung covers. Canonical
 * URL, indexability, and the Open Graph fields resolve from an explicit
 * override or a plain default — never from a template — so they are not
 * represented here.
 */
enum SeoMetadataField: string
{
    case Title = 'title';
    case Description = 'description';
}
