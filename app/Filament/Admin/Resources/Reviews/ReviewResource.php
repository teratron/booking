<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Reviews;

use App\Filament\Admin\Resources\Reviews\Pages\ListReviews;
use App\Filament\Admin\Resources\Reviews\Tables\ReviewsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Http\Controllers\Public\ReviewSubmissionController;
use App\Models\Review;
use App\Policies\ReviewPolicy;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The one screen that turns a submitted review into a published one — list,
 * filter by object/status/reported flag, and publish/reject/hide. No
 * create or edit page: a review is authored only through the public
 * submission form ({@see ReviewSubmissionController}),
 * never by hand here, and its rating/body/author fields are permanently
 * immutable once submitted ({@see ReviewPolicy}).
 */
class ReviewResource extends ScopedResource
{
    protected static ?string $model = Review::class;

    protected static string $permissionPrefix = 'object';

    protected static ?string $countryColumn = 'country_id';

    protected static ?string $territoryColumn = 'territory_id';

    protected static ?string $categoryColumn = 'object_type_id';

    /** @var list<string> */
    protected static array $eagerLoad = ['object.translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'governance';

    public static function getNavigationLabel(): string
    {
        return __('panel.reviews.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.reviews.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.reviews.title');
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
