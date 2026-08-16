<?php

declare(strict_types=1);

namespace App\Support\Api;

/**
 * The endpoint families the public REST API's launch surface groups into —
 * one case per collection/detail pair, e.g. {@see self::Objects} covers both
 * `GET /objects` and `GET /objects/{id}`. A token's `resources` scope is a
 * subset of these values, stored as Sanctum abilities so the read endpoints
 * gate on them through Sanctum's own ability check rather than a second
 * authorization mechanism.
 */
enum ApiResource: string
{
    case Countries = 'countries';
    case Territories = 'territories';
    case ObjectTypes = 'object_types';
    case Amenities = 'amenities';
    case Objects = 'objects';
    case ObjectReviews = 'object_reviews';
    case News = 'news';
    case Promotions = 'promotions';
    case Articles = 'articles';
}
