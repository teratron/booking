<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Objects\Pages;

use App\Filament\Cabinet\Resources\Objects\ObjectResource;
use App\Models\Object_;
use App\Models\ObjectTranslation;
use App\Models\User;
use App\Services\Cabinet\ObjectEditService;
use App\Services\Cabinet\ObjectPublicationEligibilityService;
use App\Services\Objects\ObjectLifecycleService;
use App\Support\Cabinet\ObjectEditOutcome;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Override;

/**
 * The owner cabinet's object-edit page — the field save plus the lifecycle
 * transitions {@see ObjectLifecycleService} already exposes. Every later
 * object-management screen in this track mirrors this page's own
 * moderation-routing shape for its own field group; nothing here is meant
 * to be reused by inheritance.
 *
 * Translated fields are collected as nested form state
 * (`translations.{locale}.*`) and reconciled against the real
 * `object_translations` rows here, exactly as the admin object form's own
 * edit page does — astrotomic's translation package has no Filament
 * relationship binding of its own to lean on.
 */
class EditObject extends EditRecord
{
    protected static string $resource = ObjectResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /**
     * Set on every `handleRecordUpdate()` call, and read straight back by
     * {@see self::getSavedNotificationTitle()} in the same request — never
     * persisted, never read across requests.
     */
    private ?ObjectEditOutcome $lastOutcome = null;

    /**
     * The default scoped query excludes soft-deleted rows, which would make
     * an archived object's own edit page unreachable — and with it, the
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
        $record = $this->currentObject();

        /** @var ObjectTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only([
                'name', 'short_description', 'full_description', 'slug', 'seo_title', 'seo_description',
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

    /**
     * A draft (or otherwise unpublished) object's edits always apply
     * directly. An edit to an already-published object is routed through
     * {@see ObjectEditService::apply()}; when that comes back queued, the
     * live record — and its translations — are left completely untouched,
     * since a pending edit must never overwrite what is already published.
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Object_ $record */
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return $record;
        }

        $service = app(ObjectEditService::class);
        $current = $service->snapshot($record);
        $proposed = $this->buildProposedSnapshot($data);

        $this->lastOutcome = $service->apply($record, $current, $proposed, $actor);

        if (! $this->lastOutcome->applied) {
            return $record;
        }

        $record->fill($data);
        $record->save();

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $fields['slug'] ??= $record->translations()->where('locale', $locale)->value('slug')
                ?? Str::slug($fields['name'] ?? (string) $record->id);

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);
        }

        return $record;
    }

    /**
     * Shapes the submitted form state into exactly what
     * {@see ObjectEditService} compares and, when applicable, submits —
     * every direct column this form manages, always present (even when
     * unset) so its shape matches {@see ObjectEditService::snapshot()}'s
     * own, plus every locale's translated bucket.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildProposedSnapshot(array $data): array
    {
        $snapshot = [];

        foreach (['address', 'latitude', 'longitude', 'country_id', 'territory_id', 'attributes'] as $key) {
            $snapshot[$key] = $data[$key] ?? null;
        }

        foreach ($this->pendingTranslations as $locale => $fields) {
            $snapshot[$locale] = $fields;
        }

        return $snapshot;
    }

    #[Override]
    protected function getSavedNotificationTitle(): ?string
    {
        return $this->lastOutcome?->applied === false
            ? __('panel.cabinet.objects.lifecycle.submitted_for_review')
            : __('panel.cabinet.objects.lifecycle.saved');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('save_as_draft', 'update', fn (Object_ $object, User $actor) => $this->service()->saveAsDraft($object, $actor)),
            $this->publishAction(),
            $this->lifecycleAction('hide', 'update', fn (Object_ $object, User $actor) => $this->service()->hide($object, $actor)),
            $this->lifecycleAction('archive', 'delete', fn (Object_ $object, User $actor) => $this->service()->archive($object, $actor))
                ->requiresConfirmation(),
            $this->lifecycleAction('restore', 'restore', fn (Object_ $object, User $actor) => $this->service()->restore($object, $actor))
                ->visible(fn (Object_ $object): bool => $object->trashed()),
        ];
    }

    /**
     * Disabled beyond the ordinary `publish` ability check until the object
     * carries every field {@see ObjectPublicationEligibilityService} treats
     * as required — a draft becomes eligible for publication only once its
     * required fields are complete, enforced here rather than trusted to
     * whoever can already see the button.
     */
    private function publishAction(): Action
    {
        return Action::make('publish')
            ->label(__('panel.objects.lifecycle.publish'))
            ->authorize(fn (Object_ $object): bool => $object->exists && (bool) auth()->user()?->can('publish', $object))
            ->disabled(fn (Object_ $object): bool => ! app(ObjectPublicationEligibilityService::class)->isEligibleForPublication($object))
            ->action(function (): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    $this->service()->publish($this->currentObject(), $actor);
                }

                $this->refreshFormData(['status']);
                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();
            });
    }

    private function lifecycleAction(string $name, string $ability, Closure $handle): Action
    {
        return Action::make($name)
            ->label(__("panel.objects.lifecycle.{$name}"))
            ->authorize(fn (Object_ $object): bool => $object->exists && (bool) auth()->user()?->can($ability, $object))
            ->action(function () use ($handle): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    $handle($this->currentObject(), $actor);
                }

                $this->refreshFormData(['status']);
                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();
            });
    }

    private function currentObject(): Object_
    {
        /** @var Object_ $record */
        $record = $this->getRecord();

        return $record;
    }

    private function service(): ObjectLifecycleService
    {
        return app(ObjectLifecycleService::class);
    }
}
