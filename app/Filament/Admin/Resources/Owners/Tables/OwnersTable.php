<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Owners\Tables;

use App\Models\Country;
use App\Models\User;
use App\Services\Owners\OwnerListAggregates;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner list. Object count, overdue-placement count, and last sign-in are
 * pulled in as selected columns on the query itself rather than resolved per
 * row — this project runs with strict lazy-loading outside production, which
 * throws rather than silently issuing one extra query per row, and a
 * per-row query would additionally blow the panel's own query budget on a
 * list of any real size.
 *
 * Account status has no stored column of its own: it is a badge derived from
 * `blocked_at` being null or not, read directly off the already-selected
 * attribute.
 */
class OwnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::withAggregates(...))
            ->columns(self::columns())
            ->filters(self::filters())
            ->recordActions([EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function withAggregates(Builder $query): Builder
    {
        return app(OwnerListAggregates::class)->apply($query);
    }

    /** @return array<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.owners.columns.name'))
                ->searchable()
                ->sortable(),

            TextColumn::make('company')
                ->label(__('panel.owners.columns.company'))
                ->searchable()
                ->placeholder('—')
                ->toggleable(),

            TextColumn::make('phone')
                ->label(__('panel.owners.columns.phone'))
                ->searchable()
                ->placeholder('—'),

            TextColumn::make('email')
                ->label(__('panel.owners.columns.email'))
                ->searchable()
                ->sortable(),

            TextColumn::make('country.code')
                ->label(__('panel.owners.columns.country'))
                ->placeholder('—')
                ->sortable(),

            TextColumn::make('objects_count')
                ->label(__('panel.owners.columns.objects_count'))
                ->numeric()
                ->sortable(),

            TextColumn::make('overdue_placements_count')
                ->label(__('panel.owners.columns.overdue_placements'))
                ->numeric()
                ->sortable(),

            TextColumn::make('created_at')
                ->label(__('panel.owners.columns.registered_at'))
                ->dateTime()
                ->sortable(),

            TextColumn::make('last_sign_in_at')
                ->label(__('panel.owners.columns.last_sign_in_at'))
                ->dateTime()
                ->placeholder('—')
                ->sortable(),

            TextColumn::make('status')
                ->label(__('panel.owners.columns.status'))
                ->getStateUsing(fn (User $record): string => $record->blocked_at === null
                    ? __('panel.owners.status.active')
                    : __('panel.owners.status.blocked'))
                ->badge()
                ->color(fn (User $record): string => $record->blocked_at === null ? 'success' : 'danger'),
        ];
    }

    /** @return array<SelectFilter|TernaryFilter> */
    private static function filters(): array
    {
        return [
            SelectFilter::make('country_id')
                ->label(__('panel.owners.columns.country'))
                ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all()),

            TernaryFilter::make('blocked_at')
                ->label(__('panel.owners.columns.status'))
                ->placeholder(__('panel.owners.filters.status_all'))
                ->trueLabel(__('panel.owners.status.blocked'))
                ->falseLabel(__('panel.owners.status.active'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('blocked_at'),
                    false: fn (Builder $query): Builder => $query->whereNull('blocked_at'),
                    blank: fn (Builder $query): Builder => $query,
                ),
        ];
    }
}
