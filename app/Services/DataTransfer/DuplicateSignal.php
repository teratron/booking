<?php

declare(strict_types=1);

namespace App\Services\DataTransfer;

/**
 * One reason an existing object was paired with an incoming import row as a
 * possible duplicate — the signal that fired (`name`, `phone`, `website`,
 * `address`, or `coordinates`) plus a human-readable detail an operator can
 * read directly in the preview, rather than a bare "possible duplicate" that
 * gives no reason to trust or dismiss the pairing.
 */
final readonly class DuplicateSignal
{
    public function __construct(
        public string $kind,
        public string $detail,
    ) {}
}
