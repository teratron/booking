<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Language;
use App\Models\User;

/**
 * The language registry is portal-wide configuration, not scoped by country
 * or territory — a Georgian-language visitor may browse Moldovan objects, so
 * no scope axis narrows which languages an administrator may see or change.
 * Gated on the same settings permission the object-type and module
 * registries use.
 */
final class LanguagePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function view(User $user, Language $language): bool
    {
        return $this->authorize($user, 'settings.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function update(User $user, Language $language): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function delete(User $user, Language $language): bool
    {
        return $this->authorize($user, 'settings.edit');
    }

    public function reorder(User $user): bool
    {
        return $this->authorize($user, 'settings.edit');
    }
}
