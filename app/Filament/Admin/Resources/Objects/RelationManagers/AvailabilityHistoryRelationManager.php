<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\RelationManagers;

use App\Models\AvailabilityHistory;
use App\Services\Objects\AvailabilityAdministrationService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The full toggle history for this object — read-only. The write path is
 * the edit page's own override/revert actions, which go through
 * {@see AvailabilityAdministrationService}; nothing
 * here creates, edits, or deletes a row, matching the append-only shape
 * the underlying table is privilege-restricted to enforce.
 */
class AvailabilityHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'availabilityHistories';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.objects.availability.history_title');
    }

    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(self::baseQuery(...))
            ->columns([
                TextColumn::make('from_status')
                    ->label(__('panel.objects.availability.from_status'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __("panel.objects.availability.{$state}"))
                    ->badge(),

                TextColumn::make('to_status')
                    ->label(__('panel.objects.availability.to_status'))
                    ->formatStateUsing(fn (string $state): string => __("panel.objects.availability.{$state}"))
                    ->badge(),

                TextColumn::make('changed_at')
                    ->label(__('panel.objects.availability.changed_at'))
                    ->dateTime(),

                TextColumn::make('changedBy.name')
                    ->label(__('panel.objects.availability.changed_by'))
                    ->placeholder('—'),

                TextColumn::make('source')
                    ->label(__('panel.objects.availability.source'))
                    ->formatStateUsing(fn (string $state): string => __("panel.objects.availability.sources.{$state}"))
                    ->badge(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @param  Builder<AvailabilityHistory>  $query
     * @return Builder<AvailabilityHistory>
     */
    private static function baseQuery(Builder $query): Builder
    {
        return $query->with('changedBy')->latest('changed_at');
    }
}
