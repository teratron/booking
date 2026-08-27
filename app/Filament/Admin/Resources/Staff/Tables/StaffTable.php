<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Staff\Tables;

use App\Filament\Admin\Resources\Owners\Tables\OwnersTable;
use App\Models\User;
use App\Services\Authorization\RoleGrantPresenter;
use App\Services\Staff\StaffListAggregates;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The staff list. Last sign-in is pulled in as a selected column on the
 * query itself rather than resolved per row, mirroring
 * {@see OwnersTable}'s own
 * reasoning: strict lazy-loading throws rather than silently issuing one
 * extra query per row.
 *
 * Account status has no stored column of its own — it is a badge derived
 * from `blocked_at`, the same source `OwnersTable` reads, worded as
 * "deactivated" rather than "blocked" to match this screen's own
 * vocabulary.
 */
class StaffTable
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
        return app(StaffListAggregates::class)->apply($query);
    }

    /** @return array<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.staff.columns.name'))
                ->searchable()
                ->sortable(),

            TextColumn::make('email')
                ->label(__('panel.staff.columns.email'))
                ->searchable()
                ->sortable(),

            TextColumn::make('roles')
                ->label(__('panel.staff.columns.roles'))
                ->getStateUsing(fn (User $record): array => app(RoleGrantPresenter::class)->activeGrantLines($record))
                ->listWithLineBreaks()
                ->limitList(3)
                ->expandableLimitedList()
                ->placeholder(__('panel.staff.columns.no_roles')),

            TextColumn::make('created_at')
                ->label(__('panel.staff.columns.created_at'))
                ->dateTime()
                ->sortable(),

            TextColumn::make('last_sign_in_at')
                ->label(__('panel.staff.columns.last_sign_in_at'))
                ->dateTime()
                ->placeholder('—')
                ->sortable(),

            TextColumn::make('status')
                ->label(__('panel.staff.columns.status'))
                ->getStateUsing(fn (User $record): string => $record->blocked_at === null
                    ? __('panel.staff.status.active')
                    : __('panel.staff.status.deactivated'))
                ->badge()
                ->color(fn (User $record): string => $record->blocked_at === null ? 'success' : 'danger'),
        ];
    }

    /** @return array<TernaryFilter> */
    private static function filters(): array
    {
        return [
            TernaryFilter::make('blocked_at')
                ->label(__('panel.staff.columns.status'))
                ->placeholder(__('panel.staff.filters.status_all'))
                ->trueLabel(__('panel.staff.status.deactivated'))
                ->falseLabel(__('panel.staff.status.active'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('blocked_at'),
                    false: fn (Builder $query): Builder => $query->whereNull('blocked_at'),
                    blank: fn (Builder $query): Builder => $query,
                ),
        ];
    }
}
