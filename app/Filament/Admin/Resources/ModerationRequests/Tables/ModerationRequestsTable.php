<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ModerationRequests\Tables;

use App\Filament\Admin\Resources\ModerationRequests\ModerationRequestResource;
use App\Models\Country;
use App\Models\ModerationRequest;
use App\Models\User;
use App\Services\Moderation\ModerationQueueService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModerationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submitted_at')
                    ->label(__('panel.moderation_queue.columns.submitted_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label(__('panel.moderation_queue.columns.owner'))
                    ->default('—'),

                TextColumn::make('target')
                    ->label(__('panel.moderation_queue.columns.object'))
                    ->getStateUsing(function (ModerationRequest $record): string {
                        $target = $record->target;
                        $name = $target?->getAttribute('name');

                        return is_string($name) && $name !== '' ? $name : "#{$record->target_id}";
                    }),

                TextColumn::make('section')
                    ->label(__('panel.moderation_queue.columns.section')),

                TextColumn::make('change_summary')
                    ->label(__('panel.moderation_queue.columns.change_summary'))
                    ->getStateUsing(fn (ModerationRequest $record): string => implode(', ', array_keys($record->proposed_data)))
                    ->limit(60),

                TextColumn::make('decision')
                    ->label(__('panel.moderation_queue.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("panel.objects.moderation.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'revision_requested' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('assignedModerator.name')
                    ->label(__('panel.moderation_queue.columns.assigned_to'))
                    ->default('—'),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label(__('panel.moderation_queue.columns.country'))
                    ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all()),

                SelectFilter::make('target_id')
                    ->label(__('panel.moderation_queue.columns.object'))
                    ->options(fn (): array => ModerationRequest::query()
                        ->with('target')
                        ->get()
                        ->pluck('target')
                        ->filter()
                        ->unique('id')
                        ->mapWithKeys(fn ($object) => [$object->id => $object->name ?? "#{$object->id}"])
                        ->all()),

                SelectFilter::make('owner_id')
                    ->label(__('panel.moderation_queue.columns.owner'))
                    ->options(fn (): array => User::query()
                        ->whereIn('id', ModerationRequest::query()->whereNotNull('owner_id')->pluck('owner_id'))
                        ->pluck('name', 'id')
                        ->all()),

                SelectFilter::make('section')
                    ->label(__('panel.moderation_queue.columns.section'))
                    ->options(fn (): array => ModerationRequest::query()
                        ->distinct()
                        ->pluck('section', 'section')
                        ->all()),

                Filter::make('submitted_between')
                    ->label(__('panel.moderation_queue.filters.submitted_between'))
                    ->schema([
                        DatePicker::make('from')->label(__('panel.moderation_queue.filters.from')),
                        DatePicker::make('until')->label(__('panel.moderation_queue.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('submitted_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('submitted_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('review')
                    ->label(__('panel.moderation_queue.actions.review'))
                    ->url(fn (ModerationRequest $record): string => ModerationRequestResource::getUrl('review', ['record' => $record])),

                Action::make('reassign')
                    ->label(__('panel.moderation_queue.actions.reassign'))
                    ->schema([
                        Select::make('moderator_id')
                            ->label(__('panel.moderation_queue.actions.reassign_to'))
                            ->options(fn (): array => User::query()
                                ->permission('moderation.edit')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (ModerationRequest $record, array $data): void {
                        $moderator = User::query()->findOrFail((int) $data['moderator_id']);
                        $actor = self::actor();

                        app(ModerationQueueService::class)->reassign($record, $moderator, $actor);

                        Notification::make()->title(__('panel.moderation_queue.notifications.reassigned'))->success()->send();
                    }),
            ])
            ->defaultSort('submitted_at', 'asc');
    }

    private static function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
