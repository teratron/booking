<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One way to reach an object's owner directly — a phone number, a messenger
 * handle, an email address. The portal's whole conversion contract terminates
 * here rather than in a booking form.
 *
 * @property string $raw_value
 */
final class ContactChannel extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Object_, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class, 'object_id');
    }

    /** @return BelongsTo<ContactChannelType, $this> */
    public function contactChannelType(): BelongsTo
    {
        return $this->belongsTo(ContactChannelType::class);
    }
}
