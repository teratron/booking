<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Models\ObjectPromotion;

/**
 * The rendered shape of one promotional label, whether resolved from a
 * currently-active {@see ObjectPromotion} grant or built
 * straight from an in-progress admin form for the "preview before saving"
 * requirement. The property list is deliberately closed: there is no field
 * here that could encode a blink flag, an animation flag, free-form CSS, or
 * an arbitrary size — only the bounded set of colours, an optional icon, a
 * fixed-enum position, and the label's own text.
 */
final readonly class PromotionLabelDecoration
{
    public function __construct(
        public string $borderColour,
        public string $textColour,
        public string $backgroundColour,
        public ?string $icon,
        public CardPosition $position,
        public ?string $text,
    ) {}
}
