<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Reviews\Tables;

use App\Filament\Admin\Resources\Reviews\ReviewResource;
use App\Models\Object_;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReviewModerationService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('object.name')
                    ->label(__('panel.reviews.columns.object'))
                    ->default('—')
                    ->searchable(),

                TextColumn::make('author_name')
                    ->label(__('panel.reviews.columns.author'))
                    ->default(__('panel.reviews.anonymous')),

                TextColumn::make('rating')
                    ->label(__('panel.reviews.columns.rating'))
                    ->formatStateUsing(fn (int $state): string => "{$state} / 5")
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('panel.reviews.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.reviews.status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'published' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                IconColumn::make('reported_at')
                    ->label(__('panel.reviews.columns.reported'))
                    ->boolean()
                    ->getStateUsing(fn (Review $record): bool => $record->reported_at !== null),

                TextColumn::make('owner_reply')
                    ->label(__('panel.reviews.columns.owner_reply'))
                    ->default('—')
                    ->limit(60),

                TextColumn::make('created_at')
                    ->label(__('panel.reviews.columns.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('object_id')
                    ->label(__('panel.reviews.columns.object'))
                    ->options(fn (): array => Object_::query()
                        ->with('translations')
                        // Scoped through the resource's own narrowed query,
                        // not a raw Review::query() — otherwise a country-
                        // scoped moderator's filter dropdown leaks another
                        // country's object names even though the table
                        // rows themselves stay correctly bounded.
                        ->whereIn('id', ReviewResource::getEloquentQuery()->pluck('object_id'))
                        ->get()
                        ->mapWithKeys(fn (Object_ $object): array => [$object->id => $object->name ?? "#{$object->id}"])
                        ->all()),

                SelectFilter::make('status')
                    ->label(__('panel.reviews.columns.status'))
                    ->options([
                        'pending' => __('panel.reviews.status.pending'),
                        'published' => __('panel.reviews.status.published'),
                        'rejected' => __('panel.reviews.status.rejected'),
                    ]),

                Filter::make('reported')
                    ->label(__('panel.reviews.filters.reported'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('reported_at'))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('panel.reviews.actions.publish'))
                    ->visible(fn (Review $record): bool => $record->status === 'pending' && self::actor()->can('publish', $record))
                    ->requiresConfirmation()
                    ->action(function (Review $record): void {
                        app(ReviewModerationService::class)->publish($record, self::actor());

                        Notification::make()->title(__('panel.reviews.notifications.published'))->success()->send();
                    }),

                Action::make('reject')
                    ->label(__('panel.reviews.actions.reject'))
                    ->visible(fn (Review $record): bool => $record->status === 'pending' && self::actor()->can('reject', $record))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('panel.reviews.actions.reason'))
                            ->required(),
                    ])
                    ->action(function (Review $record, array $data): void {
                        app(ReviewModerationService::class)->reject($record, (string) $data['reason'], self::actor());

                        Notification::make()->title(__('panel.reviews.notifications.rejected'))->success()->send();
                    }),

                Action::make('hide')
                    ->label(__('panel.reviews.actions.hide'))
                    ->visible(fn (Review $record): bool => $record->status === 'published' && self::actor()->can('hide', $record))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('panel.reviews.actions.reason'))
                            ->required(),
                    ])
                    ->action(function (Review $record, array $data): void {
                        app(ReviewModerationService::class)->hide($record, (string) $data['reason'], self::actor());

                        Notification::make()->title(__('panel.reviews.notifications.hidden'))->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
