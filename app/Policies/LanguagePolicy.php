<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Language;
use App\Models\User;

/**
 * The language registry is portal-wide configuration, not scoped by country
 * or territory — a Georgian-language visitor may browse Moldovan objects, so
 * no scope axis narrows which languages an administrator may see or change.
 *
 * Gated on `system.*`, the same permission `ModulePolicy` uses — not
 * `settings.*`, which the SEO-adjacent registries (object types, redirects)
 * still hold. Adding a language changes what every public page and every
 * form on the portal renders in; that is a system-level duty distinct from
 * a role that edits SEO metadata fields.
 */
final class LanguagePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'system.view');
    }

    public function view(User $user, Language $language): bool
    {
        return $this->authorize($user, 'system.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'system.edit');
    }

    public function update(User $user, Language $language): bool
    {
        return $this->authorize($user, 'system.edit');
    }

    public function delete(User $user, Language $language): bool
    {
        return $this->authorize($user, 'system.edit');
    }

    public function reorder(User $user): bool
    {
        return $this->authorize($user, 'system.edit');
    }
}
