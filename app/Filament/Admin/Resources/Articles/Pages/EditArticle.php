<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Articles\Pages;

use App\Exceptions\ArticleScheduleRefusedException;
use App\Filament\Admin\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\User;
use App\Services\Content\ArticleLifecycleService;
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
 * The article form's edit page — field saves plus the publish/schedule/
 * archive/restore lifecycle actions beyond an ordinary save.
 *
 * Translated fields are not real columns on `articles`; they are collected
 * as nested form state (`translations.{locale}.*`) and reconciled against
 * the real `article_translations` rows here, the same pattern
 * `EditObject`/`EditBanner` already establish for their own models.
 */
class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /**
     * The default scoped query excludes soft-deleted rows, which would make
     * an archived article's own edit page unreachable — and with it, the
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
        $record = $this->currentArticle();

        /** @var ArticleTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only([
                'title', 'summary', 'body', 'slug', 'seo_title', 'seo_description',
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
        /** @var Article $record */
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
            $this->lifecycleAction('publish', 'publish', fn (Article $article, User $actor) => $this->service()->publish($article, $actor)),
            $this->scheduleAction(),
            $this->lifecycleAction('archive', 'delete', fn (Article $article, User $actor) => $this->service()->archive($article, $actor))
                ->requiresConfirmation(),
            $this->lifecycleAction('restore', 'restore', fn (Article $article, User $actor) => $this->service()->restore($article, $actor))
                ->visible(fn (Article $article): bool => $article->trashed()),
        ];
    }

    /**
     * Distinct from {@see lifecycleAction()}: scheduling needs a date the
     * other lifecycle actions do not, and a refused schedule (a date not in
     * the future) must surface as a form notice, not a silent no-op.
     */
    private function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label(__('panel.articles.lifecycle.schedule'))
            ->authorize(fn (Article $article): bool => $article->exists && (bool) auth()->user()?->can('publish', $article))
            ->schema([
                DateTimePicker::make('publish_at')
                    ->label(__('panel.articles.form.publish_at'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    $this->service()->schedule($this->currentArticle(), Carbon::parse($data['publish_at']), $actor);
                } catch (ArticleScheduleRefusedException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.articles.lifecycle.schedule_refused'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $this->refreshFormData(['status', 'publish_at']);
                Notification::make()->title(__('panel.articles.lifecycle.applied'))->success()->send();
            });
    }

    private function lifecycleAction(string $name, string $ability, Closure $handle): Action
    {
        return Action::make($name)
            ->label(__("panel.articles.lifecycle.{$name}"))
            ->authorize(fn (Article $article): bool => $article->exists && (bool) auth()->user()?->can($ability, $article))
            ->action(function () use ($handle): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    $handle($this->currentArticle(), $actor);
                }

                $this->refreshFormData(['status', 'publish_at']);
                Notification::make()->title(__('panel.articles.lifecycle.applied'))->success()->send();
            });
    }

    private function currentArticle(): Article
    {
        /** @var Article $record */
        $record = $this->getRecord();

        return $record;
    }

    private function service(): ArticleLifecycleService
    {
        return app(ArticleLifecycleService::class);
    }
}
