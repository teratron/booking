<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\NewsItems;

use App\Filament\Cabinet\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Cabinet\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Cabinet\Resources\NewsItems\Pages\ListNewsItems;
use App\Filament\Cabinet\Resources\NewsItems\Schemas\NewsItemForm;
use App\Filament\Cabinet\Resources\NewsItems\Tables\NewsItemsTable;
use App\Filament\Cabinet\Resources\Photos\PhotoResource;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\NewsItem;
use App\Models\Scopes\ModerationScope;
use App\Services\Cabinet\NewsItemSubmissionService;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's news-authoring screen for the currently selected
 * tenant object — see {@see NewsItemSubmissionService}
 * for how a submission is routed through moderation.
 */
class NewsItemResource extends CabinetResource
{
    protected static ?string $model = NewsItem::class;

    // The cabinet dashboard's own "add_news" quick action was built ahead of
    // this resource, naming its expected route as
    // `filament.cabinet.resources.news.create` — this resource's own default
    // slug would otherwise be `news-items` (pluralised from the model's own
    // name), which would leave that quick action permanently unresolved.
    protected static ?string $slug = 'news';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.news_items.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.news_items.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.news_items.title');
    }

    /**
     * `ModerationScope` exists to keep unapproved content off public pages —
     * it must never hide an owner's own submission from their own cabinet,
     * the same reasoning `ObjectResource::getEloquentQuery()` already
     * documents. Two relations are eager-loaded, each for its own row-level
     * reason: the owning object, the same reason
     * {@see PhotoResource::getEloquentQuery()}
     * already documents (every row's `EditAction` resolves through
     * `NewsItemPolicy`, which dereferences `NewsItem::object()`); and the
     * translations astrotomic's own `title` accessor reads for the table's
     * title column — left lazy, either one fires one extra query per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(ModerationScope::class)->with(['object', 'translations']);
    }

    public static function table(Table $table): Table
    {
        return NewsItemsTable::configure(self::applyTableDefaults($table));
    }

    public static function form(Schema $schema): Schema
    {
        return NewsItemForm::configure($schema);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListNewsItems::route('/'),
            'create' => CreateNewsItem::route('/create'),
            'edit' => EditNewsItem::route('/{record}/edit'),
        ];
    }
}
