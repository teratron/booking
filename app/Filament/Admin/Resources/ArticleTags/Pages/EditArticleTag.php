<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleTags\Pages;

use App\Filament\Admin\Resources\ArticleTags\ArticleTagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArticleTag extends EditRecord
{
    protected static string $resource = ArticleTagResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
