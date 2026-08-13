<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * The fixed, small set of positions a promotional label may occupy on a
 * catalog card. Kept a closed enum rather than an open string column so an
 * administrator-authored label can never carry an arbitrary position value —
 * one of the readability guarantees this decoration is required to hold
 * regardless of who authored the label.
 */
enum CardPosition: string
{
    case TopLeft = 'top-left';
    case TopRight = 'top-right';
    case BottomLeft = 'bottom-left';
    case BottomRight = 'bottom-right';
}
