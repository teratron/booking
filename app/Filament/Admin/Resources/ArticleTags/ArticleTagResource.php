<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleTags;

use App\Filament\Admin\Resources\ArticleTags\Pages\CreateArticleTag;
use App\Filament\Admin\Resources\ArticleTags\Pages\EditArticleTag;
use App\Filament\Admin\Resources\ArticleTags\Pages\ListArticleTags;
use App\Filament\Admin\Resources\ArticleTags\Schemas\ArticleTagForm;
use App\Filament\Admin\Resources\ArticleTags\Tables\ArticleTagsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\ArticleTag;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The tag registry. Declares no scope axis — portal-wide configuration, so
 * only an unrestricted grant reaches it, like the sibling category registry.
 */
class ArticleTagResource extends ScopedResource
{
    protected static ?string $model = ArticleTag::class;

    protected static string $permissionPrefix = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'content';

    public static function getNavigationLabel(): string
    {
        return __('panel.article_tags.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.article_tags.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.article_tags.title');
    }

    public static function form(Schema $schema): Schema
    {
        return ArticleTagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticleTagsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListArticleTags::route('/'),
            'create' => CreateArticleTag::route('/create'),
            'edit' => EditArticleTag::route('/{record}/edit'),
        ];
    }
}
