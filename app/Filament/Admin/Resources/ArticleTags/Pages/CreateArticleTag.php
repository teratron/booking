<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleTags\Pages;

use App\Filament\Admin\Resources\ArticleTags\ArticleTagResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleTag extends CreateRecord
{
    protected static string $resource = ArticleTagResource::class;
}
