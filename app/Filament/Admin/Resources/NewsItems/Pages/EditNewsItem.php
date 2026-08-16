<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsItems\Pages;

use App\Exceptions\ContentScheduleRefusedException;
use App\Filament\Admin\Resources\NewsItems\NewsItemResource;
use App\Models\NewsItem;
use App\Models\NewsTranslation;
use App\Models\User;
use App\Services\Content\NewsItemLifecycleService;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Override;

/**
 * The news item form's edit page — field saves plus the publish/schedule/
 * pin/withdraw/archive/restore lifecycle actions beyond an ordinary save.
 */
class EditNewsItem extends EditRecord
{
    protected static string $resource = NewsItemResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /**
     * The default scoped query excludes soft-deleted rows, which would make
     * an archived news item's own edit page unreachable — and with it, the
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
        $record = $this->currentNewsItem();

        /** @var NewsTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only([
                'title', 'summary', 'body', 'slug', 'seo_title', 'seo_description',
                'seo_canonical_url', 'seo_indexable', 'seo_og_title', 'seo_og_description', 'seo_og_image',
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
        /** @var NewsItem $record */
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
            $this->lifecycleAction('publish', 'publish', fn (NewsItem $newsItem, User $actor) => $this->service()->publish($newsItem, $actor)),
            $this->scheduleAction(),
            $this->lifecycleAction('pin', 'update', fn (NewsItem $newsItem, User $actor) => $this->service()->pin($newsItem, $actor))
                ->visible(fn (NewsItem $newsItem): bool => ! $newsItem->is_pinned),
            $this->lifecycleAction('unpin', 'update', fn (NewsItem $newsItem, User $actor) => $this->service()->unpin($newsItem, $actor))
                ->visible(fn (NewsItem $newsItem): bool => $newsItem->is_pinned),
            $this->lifecycleAction('withdraw', 'update', fn (NewsItem $newsItem, User $actor) => $this->service()->withdraw($newsItem, $actor))
                ->requiresConfirmation(),
            $this->lifecycleAction('archive', 'delete', fn (NewsItem $newsItem, User $actor) => $this->service()->archive($newsItem, $actor))
                ->requiresConfirmation(),
            $this->lifecycleAction('restore', 'restore', fn (NewsItem $newsItem, User $actor) => $this->service()->restore($newsItem, $actor))
                ->visible(fn (NewsItem $newsItem): bool => $newsItem->trashed()),
        ];
    }

    private function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label(__('panel.news_items.lifecycle.schedule'))
            ->authorize(fn (NewsItem $newsItem): bool => $newsItem->exists && (bool) auth()->user()?->can('publish', $newsItem))
            ->schema([
                DateTimePicker::make('publish_at')
                    ->label(__('panel.news_items.form.publish_at'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    $this->service()->schedule($this->currentNewsItem(), Carbon::parse($data['publish_at']), $actor);
                } catch (ContentScheduleRefusedException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.news_items.lifecycle.schedule_refused'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $this->refreshFormData(['status', 'publish_at']);
                Notification::make()->title(__('panel.news_items.lifecycle.applied'))->success()->send();
            });
    }

    private function lifecycleAction(string $name, string $ability, Closure $handle): Action
    {
        return Action::make($name)
            ->label(__("panel.news_items.lifecycle.{$name}"))
            ->authorize(fn (NewsItem $newsItem): bool => $newsItem->exists && (bool) auth()->user()?->can($ability, $newsItem))
            ->action(function () use ($handle): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    $handle($this->currentNewsItem(), $actor);
                }

                $this->refreshFormData(['status', 'moderation_status', 'publish_at', 'is_pinned']);
                Notification::make()->title(__('panel.news_items.lifecycle.applied'))->success()->send();
            });
    }

    private function currentNewsItem(): NewsItem
    {
        /** @var NewsItem $record */
        $record = $this->getRecord();

        return $record;
    }

    private function service(): NewsItemLifecycleService
    {
        return app(NewsItemLifecycleService::class);
    }
}
