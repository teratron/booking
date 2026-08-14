<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Promotions;

use App\Filament\Cabinet\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Cabinet\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Cabinet\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Cabinet\Resources\Promotions\Schemas\PromotionForm;
use App\Filament\Cabinet\Resources\Promotions\Tables\PromotionsTable;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\Promotion;
use App\Models\Scopes\ModerationScope;
use App\Services\Cabinet\PromotionSubmissionService;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's promotion-authoring screen for the currently selected
 * tenant object — see
 * {@see PromotionSubmissionService} for how a
 * submission is routed through moderation.
 */
class PromotionResource extends CabinetResource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.promotions.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.promotions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.promotions.title');
    }

    /**
     * `ModerationScope` exists to keep unapproved content off public pages —
     * it must never hide an owner's own submission from their own cabinet,
     * the same reasoning `ObjectResource::getEloquentQuery()` already
     * documents.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(ModerationScope::class);
    }

    public static function table(Table $table): Table
    {
        return PromotionsTable::configure(self::applyTableDefaults($table));
    }

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }
}
