<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Reviews;

use App\Filament\Cabinet\Resources\Reviews\Pages\ListReviews;
use App\Filament\Cabinet\Resources\Reviews\Tables\ReviewsTable;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\Review;
use App\Policies\ReviewPolicy;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's review screen for the currently selected object — every
 * review the object has ever received, whatever its moderation status, with
 * a reply and a report action per row. There is no create or edit page:
 * review submission is out of scope for this screen (see the visitor-facing
 * write path, which this resource does not touch), and editing or deleting a
 * review is never available to an owner at all — enforced by
 * {@see ReviewPolicy}, not merely by the absence of a button.
 */
class ReviewResource extends CabinetResource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.reviews.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.reviews.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.reviews.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Eager-loads the owning object on every row: every row action's own
     * `authorize()` closure resolves through {@see ReviewPolicy},
     * which dereferences {@see Review::object()} to reach the object's scope
     * attributes. Left lazy, a review list of more than one row — the
     * ordinary case — would fire one extra query per row just to decide
     * whether its actions are visible, exactly the N+1 shape this codebase's
     * own strict-model mode is configured to refuse outright rather than let
     * through unnoticed.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('object');
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
