<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\RelationManagers;

use App\Models\PlacementHistory;
use App\Services\Placement\PlacementLifecycleService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The full placement grant history for this object — read-only. The write
 * path is the edit page's own grant/pin/unpin actions, which go through
 * {@see PlacementLifecycleService}; nothing here creates, edits, or
 * deletes a row, matching the append-only shape the granting act itself
 * guarantees.
 */
class PlacementHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'placementHistories';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.objects.placement.history_title');
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
                TextColumn::make('package.name')
                    ->label(__('panel.objects.placement.package'))
                    ->placeholder('—'),

                TextColumn::make('starts_at')
                    ->label(__('panel.objects.placement.starts_at'))
                    ->date(),

                TextColumn::make('ends_at')
                    ->label(__('panel.objects.placement.ends_at'))
                    ->date()
                    ->placeholder(__('panel.objects.placement.open')),

                TextColumn::make('amount')
                    ->label(__('panel.objects.placement.amount'))
                    ->formatStateUsing(fn (PlacementHistory $record): string => "{$record->amount} {$record->currency}"),

                TextColumn::make('status')
                    ->label(__('panel.objects.placement.ledger_status'))
                    ->formatStateUsing(fn (string $state): string => __("panel.objects.placement.ledger_status_options.{$state}"))
                    ->badge(),

                TextColumn::make('grantedBy.name')
                    ->label(__('panel.objects.placement.granted_by'))
                    ->placeholder('—'),

                TextColumn::make('comment')
                    ->label(__('panel.objects.placement.comment'))
                    ->placeholder('—')
                    ->limit(60),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @param  Builder<PlacementHistory>  $query
     * @return Builder<PlacementHistory>
     */
    private static function baseQuery(Builder $query): Builder
    {
        return $query->with(['package', 'grantedBy'])->latest('starts_at');
    }
}
