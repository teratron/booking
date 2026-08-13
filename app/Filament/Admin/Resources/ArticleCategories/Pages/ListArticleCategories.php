<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleCategories\Pages;

use App\Filament\Admin\Resources\ArticleCategories\ArticleCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArticleCategories extends ListRecords
{
    protected static string $resource = ArticleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
