<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActionJournal\Tables;

use App\Models\User;
use App\Services\Audit\AuditPresenter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

class ActionJournalTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.action_journal.columns.occurred_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('panel.action_journal.columns.actor'))
                    ->searchable()
                    ->default('—'),

                TextColumn::make('event')
                    ->label(__('panel.action_journal.columns.action'))
                    ->searchable(),

                TextColumn::make('auditable_type')
                    ->label(__('panel.action_journal.columns.target'))
                    ->formatStateUsing(fn (Audit $record): string => $record->auditable_type === null
                        ? '—'
                        : Str::headline(class_basename($record->auditable_type))." #{$record->auditable_id}"),

                TextColumn::make('old_values')
                    ->label(__('panel.action_journal.columns.previous_value'))
                    ->formatStateUsing(fn (mixed $state): string => self::formatJournalValues($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw('old_values::text ilike ?', ["%{$search}%"]))
                    ->limit(60),

                TextColumn::make('new_values')
                    ->label(__('panel.action_journal.columns.new_value'))
                    ->formatStateUsing(fn (mixed $state): string => self::formatJournalValues($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw('new_values::text ilike ?', ["%{$search}%"]))
                    ->limit(60),

                TextColumn::make('ip_address')
                    ->label(__('panel.action_journal.columns.ip_address'))
                    ->searchable(),

                TextColumn::make('user_agent')
                    ->label(__('panel.action_journal.columns.device'))
                    ->searchable()
                    ->limit(40),

                TextColumn::make('outcome')
                    ->label(__('panel.action_journal.columns.outcome'))
                    ->getStateUsing(fn (Audit $record): string => AuditPresenter::outcome($record->event))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.action_journal.outcome.{$state}"))
                    ->color(fn (string $state): string => $state === 'failure' ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('panel.action_journal.columns.actor'))
                    ->options(fn (): array => User::query()
                        ->whereIn('id', Audit::query()->whereNotNull('user_id')->pluck('user_id'))
                        ->pluck('name', 'id')
                        ->all()),

                SelectFilter::make('event')
                    ->label(__('panel.action_journal.columns.action'))
                    ->options(fn (): array => Audit::query()->distinct()->pluck('event', 'event')->all()),

                SelectFilter::make('auditable_type')
                    ->label(__('panel.action_journal.columns.target'))
                    ->options(fn (): array => Audit::query()
                        ->whereNotNull('auditable_type')
                        ->distinct()
                        ->pluck('auditable_type')
                        ->mapWithKeys(fn (string $type): array => [$type => Str::headline(class_basename($type))])
                        ->all()),

                SelectFilter::make('outcome')
                    ->label(__('panel.action_journal.columns.outcome'))
                    ->options([
                        'success' => __('panel.action_journal.outcome.success'),
                        'failure' => __('panel.action_journal.outcome.failure'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === 'failure') {
                            return $query->where(function (Builder $q): void {
                                foreach (AuditPresenter::failureSuffixes() as $suffix) {
                                    $q->orWhere('event', 'like', "%{$suffix}");
                                }
                            });
                        }

                        if ($value === 'success') {
                            return $query->where(function (Builder $q): void {
                                foreach (AuditPresenter::failureSuffixes() as $suffix) {
                                    $q->where('event', 'not like', "%{$suffix}");
                                }
                            });
                        }

                        return $query;
                    }),

                Filter::make('occurred_between')
                    ->label(__('panel.action_journal.filters.occurred_between'))
                    ->schema([
                        DatePicker::make('from')->label(__('panel.moderation_queue.filters.from')),
                        DatePicker::make('until')->label(__('panel.moderation_queue.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * `old_values`/`new_values` are cast to `json` on the vendor `Audit`
     * model, so the ordinary state is an array — but this closure sees two
     * other shapes too. While a table search is active, Filament substitutes
     * a searchable column's state with the *matched fragment* of the raw
     * column text, a plain string that is not itself JSON (confirmed by
     * dumping the value received here during a live search). And a raw,
     * not-yet-cast column read hands back the JSON-encoded string outright.
     * All three are legitimate; only the fourth case (nothing usable) is '—'.
     */
    private static function formatJournalValues(mixed $state): string
    {
        if (is_array($state)) {
            return $state === [] ? '—' : (json_encode($state) ?: '—');
        }

        if (! is_string($state) || $state === '') {
            return '—';
        }

        $decoded = json_decode($state, true);

        // A decoded array means $state was the raw JSON column text; anything
        // else (including null, meaning $state was not valid JSON) means it
        // is a search-match fragment, which is already the right thing to show.
        return is_array($decoded) ? (json_encode($decoded) ?: '—') : $state;
    }
}
