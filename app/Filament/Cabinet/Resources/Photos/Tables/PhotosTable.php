<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Photos\Tables;

use App\Models\Object_;
use App\Models\ObjectPhoto;
use App\Services\Cabinet\ObjectPhotoService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The whole photo-management surface for the current tenant object: upload
 * (a header action), reorder (the table's own native drag-to-reorder against
 * `order_column`, nothing custom needed), and per-photo caption/primary/
 * delete (row actions). Deliberately not a `SpatieMediaLibraryFileUpload`
 * form field — that component binds one collection to one `HasMedia` record
 * and has no notion of per-item caption or primary-selection fields, and
 * bending it to fit both would mean hand-syncing a second, un-related form
 * field against media rows it does not manage. A table of individual photos
 * with per-row actions covers every required capability directly, still
 * through `spatie/laravel-medialibrary`'s own upload/conversion/delete
 * mechanism underneath (`ObjectPhotoService`), never a second one.
 */
class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('order_column')
            ->reorderable('order_column')
            ->headerActions([self::uploadAction()])
            ->recordActions([
                self::setPrimaryAction(),
                self::editCaptionAction(),
                self::deleteAction(),
            ])
            ->emptyStateHeading(__('panel.cabinet.photos.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }

    /** @return array<int, ImageColumn|TextColumn|IconColumn> */
    private static function columns(): array
    {
        return [
            ImageColumn::make('thumbnail')
                ->label(__('panel.cabinet.photos.columns.photo'))
                ->getStateUsing(fn (ObjectPhoto $record): string => $record->hasGeneratedConversion('thumb')
                    ? $record->getFullUrl('thumb')
                    : $record->getFullUrl())
                ->square(),

            TextColumn::make('caption')
                ->label(__('panel.cabinet.photos.columns.caption'))
                ->getStateUsing(fn (ObjectPhoto $record): ?string => $record->getCustomProperty('caption'))
                ->placeholder('—'),

            IconColumn::make('is_primary')
                ->label(__('panel.cabinet.photos.columns.primary'))
                ->getStateUsing(fn (ObjectPhoto $record): bool => (bool) $record->getCustomProperty('is_primary', false))
                ->boolean(),
        ];
    }

    /**
     * Uploads are rejected as a whole batch, not partially, once they would
     * exceed the portal's configured maximum — an owner who selects five
     * files with only two slots left sees a clear refusal, not two silent
     * successes and three unexplained gaps.
     */
    private static function uploadAction(): Action
    {
        return Action::make('upload')
            ->label(__('panel.cabinet.photos.actions.upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize(fn (): bool => self::authorizeAgainstTenant('update'))
            ->schema([
                FileUpload::make('files')
                    ->label(__('panel.cabinet.photos.upload_form.files'))
                    ->disk('local')
                    ->directory('object-photo-uploads')
                    ->image()
                    ->multiple()
                    ->maxFiles(fn (): int => max(1, app(ObjectPhotoService::class)->remainingSlots(self::tenantObject())))
                    ->maxSize(fn (): int => app(ObjectPhotoService::class)->maxUploadKilobytes())
                    ->acceptedFileTypes(fn (): array => app(ObjectPhotoService::class)->allowedMimeTypes())
                    ->rules(fn (): array => [app(ObjectPhotoService::class)->dimensionsRule()])
                    ->required(),
            ])
            ->action(function (array $data): void {
                $object = self::tenantObject();
                $service = app(ObjectPhotoService::class);
                $paths = array_values((array) ($data['files'] ?? []));

                if (count($paths) > $service->remainingSlots($object)) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.cabinet.photos.notifications.limit_reached'))
                        ->body(__('panel.cabinet.photos.notifications.limit_reached_body', ['max' => $service->maxPhotos()]))
                        ->send();

                    return;
                }

                foreach ($paths as $path) {
                    $service->uploadFromDisk($object, (string) $path, 'local');
                }

                Notification::make()->title(__('panel.cabinet.photos.notifications.uploaded'))->success()->send();
            });
    }

    private static function setPrimaryAction(): Action
    {
        return Action::make('set_primary')
            ->label(__('panel.cabinet.photos.actions.set_primary'))
            ->icon(Heroicon::OutlinedStar)
            ->visible(fn (ObjectPhoto $record): bool => ! (bool) $record->getCustomProperty('is_primary', false))
            ->authorize(fn (ObjectPhoto $record): bool => (bool) auth()->user()?->can('update', $record))
            ->action(function (ObjectPhoto $record): void {
                $object = $record->object;

                if (! $object instanceof Object_) {
                    return;
                }

                app(ObjectPhotoService::class)->markPrimary($object, $record);

                Notification::make()->title(__('panel.cabinet.photos.notifications.primary_set'))->success()->send();
            });
    }

    private static function editCaptionAction(): Action
    {
        return Action::make('edit_caption')
            ->label(__('panel.cabinet.photos.actions.edit_caption'))
            ->icon(Heroicon::OutlinedPencil)
            ->authorize(fn (ObjectPhoto $record): bool => (bool) auth()->user()?->can('update', $record))
            ->fillForm(fn (ObjectPhoto $record): array => ['caption' => $record->getCustomProperty('caption')])
            ->schema([
                Textarea::make('caption')
                    ->label(__('panel.cabinet.photos.caption_form.caption'))
                    ->maxLength(255),
            ])
            ->action(function (ObjectPhoto $record, array $data): void {
                app(ObjectPhotoService::class)->setCaption($record, $data['caption'] ?? null);

                Notification::make()->title(__('panel.cabinet.photos.notifications.caption_saved'))->success()->send();
            });
    }

    private static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('panel.cabinet.photos.actions.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->authorize(fn (ObjectPhoto $record): bool => (bool) auth()->user()?->can('delete', $record))
            ->action(function (ObjectPhoto $record): void {
                app(ObjectPhotoService::class)->delete($record);

                Notification::make()->title(__('panel.cabinet.photos.notifications.deleted'))->success()->send();
            });
    }

    private static function authorizeAgainstTenant(string $ability): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Object_ && (bool) auth()->user()?->can($ability, $tenant);
    }

    /**
     * Navigation and other panel chrome can render before tenant resolution
     * runs, so a defaulted, never-persisted object stands in rather than
     * this throwing — the same defensive shape `CabinetResource`'s own
     * navigation-visibility helpers use.
     */
    private static function tenantObject(): Object_
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Object_ ? $tenant : new Object_;
    }
}
