<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation would leave the chief administrator role with no
 * holder — the exact way a permission edit can lock every administrator out
 * of the panel that manages permissions.
 */
final class UnrevocableGrantException extends RuntimeException {}
