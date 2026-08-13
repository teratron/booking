<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleTags\Pages;

use App\Filament\Admin\Resources\ArticleTags\ArticleTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArticleTags extends ListRecords
{
    protected static string $resource = ArticleTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
