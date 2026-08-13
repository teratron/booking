<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PromotionLabels;

use App\Filament\Admin\Resources\PromotionLabels\Pages\CreatePromotionLabel;
use App\Filament\Admin\Resources\PromotionLabels\Pages\EditPromotionLabel;
use App\Filament\Admin\Resources\PromotionLabels\Pages\ListPromotionLabels;
use App\Filament\Admin\Resources\PromotionLabels\Schemas\PromotionLabelForm;
use App\Filament\Admin\Resources\PromotionLabels\Tables\PromotionLabelsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\PromotionLabel;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The open, back-office-configured set of card decorations an administrator
 * may grant an object independently of its placement package. Declares no
 * scope axis: a label definition is not owned by any one country, territory,
 * or object category — like the placement package and tier registries it
 * sits beside in navigation, only which object currently carries a label is
 * a per-object concern, not this registry itself.
 */
class PromotionLabelResource extends ScopedResource
{
    protected static ?string $model = PromotionLabel::class;

    protected static string $permissionPrefix = 'advertising';

    /** @var list<string> */
    protected static array $eagerLoad = ['translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'advertising';

    public static function getNavigationLabel(): string
    {
        return __('panel.promotion_labels.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.promotion_labels.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.promotion_labels.title');
    }

    public static function form(Schema $schema): Schema
    {
        return PromotionLabelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionLabelsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPromotionLabels::route('/'),
            'create' => CreatePromotionLabel::route('/create'),
            'edit' => EditPromotionLabel::route('/{record}/edit'),
        ];
    }
}
