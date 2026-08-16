<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * The closed set of entity kinds the portal maintains SEO metadata for.
 * Backs both `seo_metadata_templates.entity_type` and every internal
 * lookup into it — a code-level vocabulary, not an administrator-editable
 * registry.
 */
enum SeoEntityType: string
{
    case Territory = 'territory';
    case ObjectType = 'object_type';
    case Object_ = 'object';
    case Promotion = 'promotion';
    case NewsItem = 'news_item';
    case Article = 'article';
}
