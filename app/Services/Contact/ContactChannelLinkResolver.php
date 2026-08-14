<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Models\ContactChannelType;

/**
 * Turns a contact channel's own raw stored value — a phone number, a
 * handle — into the actionable deep link a visitor's tap or click actually
 * follows. The substitution rule lives entirely on the channel type's own
 * registry row (`link_template`), never in this class as a per-channel
 * branch: adding a channel ("other social networks") is a data operation
 * a caller reaches without this resolver changing at all.
 */
final class ContactChannelLinkResolver
{
    private const string VALUE_PLACEHOLDER = '{value}';

    /**
     * Null when the type is inactive (an administrator has retired the
     * channel from the registry) or carries no template — never a broken
     * link, and never a caller-visible distinction between "inactive" and
     * "misconfigured": either way, there is nothing safe to link to.
     */
    public function resolve(ContactChannelType $type, string $rawValue): ?string
    {
        if (! $type->is_active) {
            return null;
        }

        if ($type->link_template === null || $type->link_template === '') {
            return null;
        }

        return str_replace(self::VALUE_PLACEHOLDER, $rawValue, $type->link_template);
    }
}
