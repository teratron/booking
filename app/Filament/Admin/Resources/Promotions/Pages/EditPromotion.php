<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Promotions\Pages;

use App\Exceptions\ContentScheduleRefusedException;
use App\Filament\Admin\Resources\Promotions\PromotionResource;
use App\Models\Promotion;
use App\Models\PromotionTranslation;
use App\Models\User;
use App\Services\Content\PromotionLifecycleService;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Override;

/**
 * The promotion form's edit page — field saves plus the publish/schedule/
 * archive/delete/restore lifecycle actions beyond an ordinary save.
 */
class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /**
     * The default scoped query excludes soft-deleted rows, which would make
     * a deleted promotion's own edit page unreachable — and with it, the
     * restore action that is the only way back.
     */
    #[Override]
    protected function resolveRecord(int|string $key): Model
    {
        $record = static::getResource()::getEloquentQuery()
            ->withTrashed()
            ->whereKey($key)
            ->first();

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel($this->getModel(), [$key]);
        }

        return $record;
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->currentPromotion();

        /** @var PromotionTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only([
                'title', 'summary', 'slug', 'seo_title', 'seo_description',
            ]);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingTranslations = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Promotion $record */
        $record->update($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $fields['slug'] ??= $record->translations()->where('locale', $locale)->value('slug')
                ?? Str::slug($fields['title'] ?? (string) $record->id);

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);
        }

        return $record;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('publish', 'publish', fn (Promotion $promotion, User $actor) => $this->service()->publish($promotion, $actor)),
            $this->scheduleAction(),
            $this->lifecycleAction('archive', 'update', fn (Promotion $promotion, User $actor) => $this->service()->archive($promotion, $actor))
                ->requiresConfirmation(),
            $this->lifecycleAction('delete', 'delete', fn (Promotion $promotion, User $actor) => $this->service()->delete($promotion, $actor))
                ->requiresConfirmation(),
            $this->lifecycleAction('restore', 'restore', fn (Promotion $promotion, User $actor) => $this->service()->restore($promotion, $actor))
                ->visible(fn (Promotion $promotion): bool => $promotion->trashed()),
        ];
    }

    private function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label(__('panel.promotions.lifecycle.schedule'))
            ->authorize(fn (Promotion $promotion): bool => $promotion->exists && (bool) auth()->user()?->can('publish', $promotion))
            ->schema([
                DatePicker::make('starts_at')
                    ->label(__('panel.promotions.form.starts_at'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    $this->service()->schedule($this->currentPromotion(), Carbon::parse($data['starts_at']), $actor);
                } catch (ContentScheduleRefusedException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.promotions.lifecycle.schedule_refused'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $this->refreshFormData(['status', 'starts_at']);
                Notification::make()->title(__('panel.promotions.lifecycle.applied'))->success()->send();
            });
    }

    private function lifecycleAction(string $name, string $ability, Closure $handle): Action
    {
        return Action::make($name)
            ->label(__("panel.promotions.lifecycle.{$name}"))
            ->authorize(fn (Promotion $promotion): bool => $promotion->exists && (bool) auth()->user()?->can($ability, $promotion))
            ->action(function () use ($handle): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    $handle($this->currentPromotion(), $actor);
                }

                $this->refreshFormData(['status', 'moderation_status', 'starts_at']);
                Notification::make()->title(__('panel.promotions.lifecycle.applied'))->success()->send();
            });
    }

    private function currentPromotion(): Promotion
    {
        /** @var Promotion $record */
        $record = $this->getRecord();

        return $record;
    }

    private function service(): PromotionLifecycleService
    {
        return app(PromotionLifecycleService::class);
    }
}
