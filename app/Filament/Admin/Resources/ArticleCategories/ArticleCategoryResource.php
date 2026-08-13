<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ArticleCategories;

use App\Filament\Admin\Resources\ArticleCategories\Pages\CreateArticleCategory;
use App\Filament\Admin\Resources\ArticleCategories\Pages\EditArticleCategory;
use App\Filament\Admin\Resources\ArticleCategories\Pages\ListArticleCategories;
use App\Filament\Admin\Resources\ArticleCategories\Schemas\ArticleCategoryForm;
use App\Filament\Admin\Resources\ArticleCategories\Tables\ArticleCategoriesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\ArticleCategory;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The editorial taxonomy registry. Declares no scope axis — like the object
 * type registry, this is portal-wide configuration, so only an unrestricted
 * grant reaches it.
 */
class ArticleCategoryResource extends ScopedResource
{
    protected static ?string $model = ArticleCategory::class;

    protected static string $permissionPrefix = 'content';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'content';

    public static function getNavigationLabel(): string
    {
        return __('panel.article_categories.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.article_categories.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.article_categories.title');
    }

    public static function form(Schema $schema): Schema
    {
        return ArticleCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticleCategoriesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListArticleCategories::route('/'),
            'create' => CreateArticleCategory::route('/create'),
            'edit' => EditArticleCategory::route('/{record}/edit'),
        ];
    }
}
