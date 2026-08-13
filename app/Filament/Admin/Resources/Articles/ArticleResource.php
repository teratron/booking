<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Articles;

use App\Filament\Admin\Resources\Articles\Pages\CreateArticle;
use App\Filament\Admin\Resources\Articles\Pages\EditArticle;
use App\Filament\Admin\Resources\Articles\Pages\ListArticles;
use App\Filament\Admin\Resources\Articles\Schemas\ArticleForm;
use App\Filament\Admin\Resources\Articles\Tables\ArticlesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\Article;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Administrator-authored editorial content. Declares no scope axis: articles
 * are not owned by any one country, territory, or object category — they
 * relate to several of each — so, like `ArticlePolicy`, only an
 * unrestricted grant reaches this resource.
 */
class ArticleResource extends ScopedResource
{
    protected static ?string $model = Article::class;

    protected static string $permissionPrefix = 'content';

    /**
     * `author`/`category`/`tags`/`objects`/`territories` are read by table
     * columns and form multi-selects alike — strict mode turns the lazy
     * load any of them would otherwise trigger into a thrown exception, the
     * exact shape `ScopedResource`'s own docblock warns about.
     *
     * @var list<string>
     */
    protected static array $eagerLoad = [
        'translations',
        'author',
        'category.translations',
        'tags',
        'objects.translations',
        'territories.translations',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'content';

    public static function getNavigationLabel(): string
    {
        return __('panel.articles.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.articles.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.articles.title');
    }

    public static function form(Schema $schema): Schema
    {
        return ArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticlesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
