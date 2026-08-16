<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SeoMetadataTemplate;
use App\Models\User;

/**
 * Metadata templates are portal-wide addressing configuration, not scoped
 * to a country, territory, or category — the same reasoning
 * {@see RedirectPolicy} applies to the redirect table.
 */
final class SeoMetadataTemplatePolicy extends ScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'seo.view');
    }

    public function view(User $user, SeoMetadataTemplate $template): bool
    {
        return $this->authorize($user, 'seo.view');
    }

    public function create(User $user): bool
    {
        return $this->authorize($user, 'seo.edit');
    }

    public function update(User $user, SeoMetadataTemplate $template): bool
    {
        return $this->authorize($user, 'seo.edit');
    }

    public function delete(User $user, SeoMetadataTemplate $template): bool
    {
        return $this->authorize($user, 'seo.edit');
    }
}
