<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CatalogFilterPromotions\Pages;

use App\Filament\Admin\Resources\CatalogFilterPromotions\CatalogFilterPromotionResource;
use App\Services\Seo\IndexationPolicy;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCatalogFilterPromotion extends EditRecord
{
    protected static string $resource = CatalogFilterPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->after(fn () => app(IndexationPolicy::class)->forgetCache()),
        ];
    }

    /**
     * Not a real method override — Filament's page lifecycle discovers this
     * hook by name via `method_exists()`, not through the class hierarchy,
     * so `#[Override]` does not apply here.
     */
    protected function afterSave(): void
    {
        app(IndexationPolicy::class)->forgetCache();
    }
}
