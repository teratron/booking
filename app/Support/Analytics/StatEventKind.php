<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * The six coarse interaction categories `stat_events.kind`'s check
 * constraint allows. The specification's finer-grained list — card view,
 * page view, photo view, phone/Viber/Telegram/WhatsApp/Messenger/website/
 * social clicks, banner impression, banner click — collapses onto these six:
 * every contact-channel click is a {@see self::ContactClick} row, with
 * `contact_channel_type_id` carrying which channel was used rather than a
 * seventh (or thirteenth) kind value.
 */
enum StatEventKind: string
{
    case ObjectCardView = 'object_card_view';
    case ObjectPageView = 'object_page_view';
    case PhotoView = 'photo_view';
    case ContactClick = 'contact_click';
    case BannerImpression = 'banner_impression';
    case BannerClick = 'banner_click';
}
