<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Reviews\Tables;

use App\Filament\Cabinet\Resources\Reviews\ReviewResource;
use App\Models\Review;
use App\Models\User;
use App\Services\Cabinet\ReviewInteractionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The whole review-management surface for the current tenant object: every
 * review it has ever received, whatever its moderation status, with a reply
 * and a report row action. There is deliberately no edit or delete action —
 * see {@see ReviewResource}'s own
 * docblock for why.
 */
class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('created_at', 'desc')
            ->recordActions([self::replyAction(), self::reportAction()])
            ->emptyStateHeading(__('panel.cabinet.reviews.empty'))
            ->emptyStateIcon(Heroicon::OutlinedStar);
    }

    /** @return list<TextColumn|IconColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('rating')
                ->label(__('panel.cabinet.reviews.columns.rating')),

            TextColumn::make('author_name')
                ->label(__('panel.cabinet.reviews.columns.author'))
                // `author_id` is a genuinely nullable FK (a guest reviewer
                // carries none); Larastan types every BelongsTo relation as
                // non-nullable regardless of the column's real nullability,
                // so the FK is checked directly rather than
                // nullsafe-accessing the relation.
                ->getStateUsing(function (Review $record): string {
                    if ($record->author_name !== null) {
                        return $record->author_name;
                    }

                    if ($record->author_id !== null) {
                        return $record->author->name ?? __('panel.cabinet.reviews.author_fallback');
                    }

                    return __('panel.cabinet.reviews.author_fallback');
                }),

            TextColumn::make('body')
                ->label(__('panel.cabinet.reviews.columns.body'))
                ->limit(80)
                ->wrap(),

            TextColumn::make('status')
                ->label(__('panel.cabinet.reviews.columns.status'))
                ->badge()
                ->formatStateUsing(fn (string $state): string => __("panel.cabinet.reviews.status.{$state}")),

            TextColumn::make('owner_reply')
                ->label(__('panel.cabinet.reviews.columns.owner_reply'))
                ->limit(80)
                ->wrap()
                ->placeholder('—'),

            IconColumn::make('reported_at')
                ->label(__('panel.cabinet.reviews.columns.reported'))
                ->getStateUsing(fn (Review $record): bool => $record->reported_at !== null)
                ->boolean(),

            TextColumn::make('created_at')
                ->label(__('panel.cabinet.reviews.columns.created_at'))
                ->dateTime(),
        ];
    }

    /**
     * An owner may post exactly one reply per review — the action hides
     * itself once {@see Review::owner_reply} is already set, and
     * {@see ReviewInteractionService::reply()} refuses a second attempt even
     * if this action were somehow invoked again on the same row.
     */
    private static function replyAction(): Action
    {
        return Action::make('reply')
            ->label(__('panel.cabinet.reviews.actions.reply'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->visible(fn (Review $record): bool => $record->owner_reply === null)
            ->authorize(fn (Review $record): bool => (bool) auth()->user()?->can('reply', $record))
            ->schema([
                Textarea::make('owner_reply')
                    ->label(__('panel.cabinet.reviews.reply_form.owner_reply'))
                    ->required()
                    ->maxLength(5000),
            ])
            ->action(function (Review $record, array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                app(ReviewInteractionService::class)->reply($record, $actor, (string) $data['owner_reply']);

                Notification::make()->title(__('panel.cabinet.reviews.notifications.replied'))->success()->send();
            });
    }

    /**
     * An owner may flag a review for administrator attention once — the
     * action hides itself once {@see Review::reported_at} is already set,
     * and {@see ReviewInteractionService::report()} refuses a second attempt
     * even if this action were somehow invoked again on the same row.
     */
    private static function reportAction(): Action
    {
        return Action::make('report')
            ->label(__('panel.cabinet.reviews.actions.report'))
            ->icon('heroicon-o-flag')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Review $record): bool => $record->reported_at === null)
            ->authorize(fn (Review $record): bool => (bool) auth()->user()?->can('report', $record))
            ->schema([
                Textarea::make('report_reason')
                    ->label(__('panel.cabinet.reviews.report_form.report_reason'))
                    ->required()
                    ->maxLength(2000),
            ])
            ->action(function (Review $record, array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                app(ReviewInteractionService::class)->report($record, $actor, (string) $data['report_reason']);

                Notification::make()->title(__('panel.cabinet.reviews.notifications.reported'))->success()->send();
            });
    }
}
