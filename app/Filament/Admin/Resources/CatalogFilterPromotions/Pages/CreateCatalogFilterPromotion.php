<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CatalogFilterPromotions\Pages;

use App\Filament\Admin\Resources\CatalogFilterPromotions\CatalogFilterPromotionResource;
use App\Services\Seo\IndexationPolicy;
use Filament\Resources\Pages\CreateRecord;

class CreateCatalogFilterPromotion extends CreateRecord
{
    protected static string $resource = CatalogFilterPromotionResource::class;

    /**
     * A new promotion must make its catalog view indexable immediately —
     * a specialist would otherwise have no way to know whether the
     * cached allowlist has caught up. Not a real method override —
     * Filament's page lifecycle discovers this hook by name via
     * `method_exists()`, not through the class hierarchy, so `#[Override]`
     * does not apply here.
     */
    protected function afterCreate(): void
    {
        app(IndexationPolicy::class)->forgetCache();
    }
}
