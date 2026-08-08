<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ModerationRequests\Pages;

use App\Exceptions\ModerationDecisionRefusedException;
use App\Filament\Admin\Resources\ModerationRequests\ModerationRequestResource;
use App\Models\ModerationRequest;
use App\Models\User;
use App\Services\Moderation\ModerationDecisionService;
use App\Services\Settings\SettingsRepository;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * The moderation queue's entry point: published versus proposed values side
 * by side, changed values marked, and the full decision set — approve,
 * reject with a reason, request revision with a comment, or partially
 * accept when the portal setting allows it.
 *
 * A decided request renders read-only: the action list is empty once
 * `decision` is no longer `pending`, since re-deciding a settled request is
 * exactly the append-only invariant this panel enforces everywhere else.
 */
class ReviewModerationRequest extends ViewRecord
{
    protected static string $resource = ModerationRequestResource::class;

    protected string $view = 'filament.admin.resources.moderation-requests.pages.review-moderation-request';

    public function getTitle(): string
    {
        return __('panel.moderation_review.title');
    }

    /**
     * @return list<array{key: string, previous: mixed, proposed: mixed, changed: bool}>
     */
    public function diff(): array
    {
        $request = $this->moderationRequest();

        $previous = $request->previous_data ?? [];
        $proposed = $request->proposed_data;
        $keys = array_unique([...array_keys($previous), ...array_keys($proposed)]);

        return array_values(array_map(fn (string $key): array => [
            'key' => $key,
            'previous' => $previous[$key] ?? null,
            'proposed' => $proposed[$key] ?? null,
            'changed' => ($previous[$key] ?? null) !== ($proposed[$key] ?? null),
        ], $keys));
    }

    private function moderationRequest(): ModerationRequest
    {
        /** @var ModerationRequest $request */
        $request = $this->getRecord();

        return $request;
    }

    public function isImageValue(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $value);
    }

    protected function getHeaderActions(): array
    {
        $request = $this->moderationRequest();

        if ($request->decision !== 'pending') {
            return [];
        }

        return [
            $this->approveAction(),
            $this->rejectAction(),
            $this->requestRevisionAction(),
            $this->partiallyAcceptAction(),
        ];
    }

    private function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('panel.moderation_review.actions.approve'))
            ->color('success')
            ->authorize('update')
            ->requiresConfirmation()
            ->action(function (): void {
                app(ModerationDecisionService::class)->approve($this->moderationRequest(), $this->actor());

                Notification::make()->title(__('panel.moderation_review.notifications.approved'))->success()->send();
                $this->redirect(ModerationRequestResource::getUrl());
            });
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('panel.moderation_review.actions.reject'))
            ->color('danger')
            ->authorize('update')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->label(__('panel.moderation_review.fields.reason'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                app(ModerationDecisionService::class)->reject($this->moderationRequest(), $data['reason'], $this->actor());

                Notification::make()->title(__('panel.moderation_review.notifications.rejected'))->success()->send();
                $this->redirect(ModerationRequestResource::getUrl());
            });
    }

    private function requestRevisionAction(): Action
    {
        return Action::make('request_revision')
            ->label(__('panel.moderation_review.actions.request_revision'))
            ->color('warning')
            ->authorize('update')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('comment')
                    ->label(__('panel.moderation_review.fields.comment'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                app(ModerationDecisionService::class)->requestRevision($this->moderationRequest(), $data['comment'], $this->actor());

                Notification::make()->title(__('panel.moderation_review.notifications.revision_requested'))->success()->send();
                $this->redirect(ModerationRequestResource::getUrl());
            });
    }

    private function partiallyAcceptAction(): Action
    {
        $request = $this->moderationRequest();

        return Action::make('partially_accept')
            ->label(__('panel.moderation_review.actions.partially_accept'))
            ->color('info')
            ->authorize('update')
            ->requiresConfirmation()
            ->visible(fn (): bool => (bool) app(SettingsRepository::class)->get('moderation.partial_acceptance_enabled'))
            ->schema([
                CheckboxList::make('fields')
                    ->label(__('panel.moderation_review.fields.accepted_fields'))
                    ->options(array_combine(array_keys($request->proposed_data), array_keys($request->proposed_data)))
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    app(ModerationDecisionService::class)->partiallyAccept($this->moderationRequest(), $data['fields'], $this->actor());
                } catch (ModerationDecisionRefusedException $exception) {
                    Notification::make()->title(__('panel.moderation_review.notifications.partial_refused'))->body($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('panel.moderation_review.notifications.partially_accepted'))->success()->send();
                $this->redirect(ModerationRequestResource::getUrl());
            });
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
