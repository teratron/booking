<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Photos;

use App\Filament\Cabinet\Resources\Photos\Pages\ListPhotos;
use App\Filament\Cabinet\Resources\Photos\Tables\PhotosTable;
use App\Filament\Cabinet\Support\CabinetResource;
use App\Models\ObjectPhoto;
use App\Policies\ObjectPhotoPolicy;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's photo-gallery management screen for the currently
 * selected object — upload, delete, reorder, caption, and primary-photo
 * selection, all against `ObjectPhoto`'s own tenant-scoped view of the
 * object's `photos` media collection (see that model's docblock for why a
 * dedicated subclass exists at all). Everything lives on the one list page:
 * upload is a table header action, and caption/primary/delete are row
 * actions — there is no edit page, since nothing here is a record with its
 * own set of columns to edit outside those actions.
 */
class PhotoResource extends CabinetResource
{
    protected static ?string $model = ObjectPhoto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.photos.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.cabinet.photos.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.cabinet.photos.title');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Eager-loads the owning object on every row: every row action's own
     * `authorize()` closure resolves through {@see ObjectPhotoPolicy},
     * which dereferences {@see ObjectPhoto::object()} to reach the object's
     * scope attributes. Left lazy, a gallery of more than one photograph —
     * the ordinary case — would fire one extra query per row just to
     * decide whether its actions are visible, exactly the N+1 shape this
     * codebase's own strict-model mode is configured to refuse outright
     * rather than let through unnoticed.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('object');
    }

    public static function table(Table $table): Table
    {
        return PhotosTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPhotos::route('/'),
        ];
    }
}
