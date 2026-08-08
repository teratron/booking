<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ModerationRequests;

use App\Filament\Admin\Resources\ModerationRequests\Pages\ListModerationRequests;
use App\Filament\Admin\Resources\ModerationRequests\Pages\ReviewModerationRequest;
use App\Filament\Admin\Resources\ModerationRequests\Tables\ModerationRequestsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\ModerationRequest;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The moderation queue: every pending (and past) change request, oldest
 * first. Country-scoped on `country_id`, denormalized onto the request at
 * submission time since the target it concerns is polymorphic. No create,
 * edit, or delete page — a request is produced by the submission pipeline
 * and settled through the review page, never authored or removed by hand
 * here.
 */
class ModerationRequestResource extends ScopedResource
{
    protected static ?string $model = ModerationRequest::class;

    protected static string $permissionPrefix = 'moderation';

    protected static ?string $countryColumn = 'country_id';

    /** @var list<string> */
    protected static array $eagerLoad = ['target', 'owner', 'submittedBy', 'assignedModerator', 'country.translations'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'governance';

    public static function getNavigationLabel(): string
    {
        return __('panel.moderation_queue.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.moderation_queue.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.moderation_queue.title');
    }

    public static function table(Table $table): Table
    {
        return ModerationRequestsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListModerationRequests::route('/'),
            'review' => ReviewModerationRequest::route('/{record}/review'),
        ];
    }
}
