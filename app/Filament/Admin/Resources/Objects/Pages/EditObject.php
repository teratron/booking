<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\Pages;

use App\Exceptions\AvailabilityRevertRefusedException;
use App\Exceptions\BumpRefusedException;
use App\Exceptions\PermanentDeletionRefusedException;
use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Models\Object_;
use App\Models\ObjectPlacement;
use App\Models\ObjectTranslation;
use App\Models\PlacementPackage;
use App\Models\Territory;
use App\Models\User;
use App\Services\Objects\AvailabilityAdministrationService;
use App\Services\Objects\ObjectLifecycleService;
use App\Services\Placement\BumpService;
use App\Services\Seo\RedirectRegistrar;
use App\Services\Settings\SettingsRepository;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Override;

/**
 * The object form's edit page — field saves plus the eight lifecycle actions
 * `[TZ]` §104 names beyond an ordinary save.
 *
 * Translated fields are not real columns on `objects`; they are collected as
 * nested form state (`translations.{locale}.*`) and reconciled against the
 * real `object_translations` rows here, since the translation package has no
 * Filament relationship binding of its own to lean on.
 */
class EditObject extends EditRecord
{
    protected static string $resource = ObjectResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /**
     * The default scoped query excludes soft-deleted rows, which would make
     * an archived object's own edit page unreachable — and with it, the
     * restore action that is the only way back. Trashed records are
     * included here; the restore action itself stays gated on the `restore`
     * ability, so reachability is not the same as being editable.
     */
    #[Override]
    protected function resolveRecord(int|string $key): Model
    {
        $this->mountParentRecord();

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
        /** @var Object_ $record */
        $record->update($data);

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            $previousSlug = $record->translations()->where('locale', $locale)->value('slug');

            $fields['slug'] ??= $previousSlug ?? Str::slug($fields['name'] ?? (string) $record->id);

            $newPath = "o/{$fields['slug']}";
            $oldPath = $previousSlug !== null ? "o/{$previousSlug}" : null;
            $redirects = app(RedirectRegistrar::class);

            // A refused reuse skips only this locale's write — the rest of
            // the form's changes still save, matching this page's own
            // per-lifecycle-action pattern of a notification in place of a
            // hard failure for a foreseeable, correctable input mistake.
            if ($newPath !== $oldPath && $redirects->isClaimed($locale, $newPath)) {
                Notification::make()
                    ->danger()
                    ->title(__('panel.objects.form.slug_claimed_by_redirect', ['path' => $newPath]))
                    ->send();

                continue;
            }

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);

            $redirects->registerPathChange($locale, $oldPath, $newPath);
        }

        return $record;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->lifecycleAction('save_as_draft', 'update', fn (Object_ $object, User $actor) => $this->service()->saveAsDraft($object, $actor)),
            $this->lifecycleAction('publish', 'publish', fn (Object_ $object, User $actor) => $this->service()->publish($object, $actor)),
            $this->lifecycleAction('hide', 'update', fn (Object_ $object, User $actor) => $this->service()->hide($object, $actor)),
            $this->returnForRevisionAction(),
            $this->lifecycleAction('archive', 'delete', fn (Object_ $object, User $actor) => $this->service()->archive($object, $actor))
                ->requiresConfirmation(),
            $this->lifecycleAction('restore', 'restore', fn (Object_ $object, User $actor) => $this->service()->restore($object, $actor))
                ->visible(fn (Object_ $object): bool => $object->trashed()),
            $this->permanentlyDeleteAction(),
            $this->duplicateAction(),
            $this->transferOwnershipAction(),
            $this->overrideAvailabilityAction(),
            $this->revertAvailabilityAction(),
            $this->bumpAction(),
        ];
    }

    /**
     * Restricted to the chief administrator (`Object_Policy::forceDelete()`)
     * and visible only once an object is already archived — permanent
     * deletion is the last step of the archive flow, not an alternative to
     * it. The password field is the re-authentication `[TZ]` §75 asks for by
     * name; a confirmation click alone is not that gate.
     */
    private function permanentlyDeleteAction(): Action
    {
        return Action::make('permanently_delete')
            ->label(__('panel.objects.lifecycle.permanently_delete'))
            ->color('danger')
            ->authorize(fn (Object_ $object): bool => (bool) auth()->user()?->can('forceDelete', $object))
            ->visible(fn (Object_ $object): bool => $object->trashed())
            ->requiresConfirmation()
            ->schema([
                TextInput::make('password')
                    ->label(__('panel.objects.lifecycle.reauthenticate_password'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    $this->service()->permanentlyDelete($this->currentObject(), $actor, $data['password']);
                } catch (PermanentDeletionRefusedException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.objects.lifecycle.permanently_delete_refused'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();

                $this->redirect(ObjectResource::getUrl());
            });
    }

    /**
     * Goes through {@see AvailabilityAdministrationService} rather than the
     * ordinary `handleRecordUpdate()` save path — that path would skip the
     * paired `availability_histories` row and the moderation-bypass this
     * write is required to keep.
     */
    private function overrideAvailabilityAction(): Action
    {
        return Action::make('override_availability')
            ->label(__('panel.objects.availability.override'))
            ->authorize(fn (Object_ $object): bool => (bool) auth()->user()?->can('update', $object))
            ->schema([
                Select::make('availability_status')
                    ->label(__('panel.objects.availability.new_status'))
                    ->options([
                        'available' => __('panel.objects.availability.available'),
                        'unavailable' => __('panel.objects.availability.unavailable'),
                        'unspecified' => __('panel.objects.availability.unspecified'),
                    ])
                    ->required(),
                Textarea::make('comment')
                    ->label(__('panel.objects.availability.comment')),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    app(AvailabilityAdministrationService::class)->override(
                        $this->currentObject(),
                        $data['availability_status'],
                        $actor,
                        $data['comment'] ?? null,
                    );
                }

                $this->refreshFormData(['availability_status']);
                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();
            });
    }

    private function revertAvailabilityAction(): Action
    {
        return Action::make('revert_availability')
            ->label(__('panel.objects.availability.revert'))
            ->authorize(fn (Object_ $object): bool => (bool) auth()->user()?->can('update', $object))
            ->visible(fn (Object_ $object): bool => $object->availability_previous_status !== null)
            ->requiresConfirmation()
            ->action(function (): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    app(AvailabilityAdministrationService::class)->revert($this->currentObject(), $actor);
                } catch (AvailabilityRevertRefusedException) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.objects.availability.revert_refused'))
                        ->send();

                    return;
                }

                $this->refreshFormData(['availability_status']);
                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();
            });
    }

    /**
     * Administrator-initiated only — the service accepts all five bump
     * types the specification names, but the owner-initiated caller arrives
     * with the cabinet in a later phase. Scope defaults to the object's own
     * territory, matching the specification's own flow ("resolve scope:
     * object's city / district / resort"); no scope picker is offered here.
     */
    private function bumpAction(): Action
    {
        return Action::make('bump')
            ->label(__('panel.objects.lifecycle.bump'))
            ->authorize(fn (Object_ $object): bool => (bool) auth()->user()?->can('update', $object))
            ->visible(function (Object_ $object): bool {
                $placement = $object->placement;

                return $placement instanceof ObjectPlacement
                    && $placement->package instanceof PlacementPackage
                    && $placement->package->bump_allowed;
            })
            ->schema([
                Textarea::make('comment')
                    ->label(__('panel.objects.lifecycle.bump_comment')),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();
                $object = $this->currentObject();
                $scope = Territory::query()->find($object->territory_id);

                if (! $actor instanceof User || ! $scope instanceof Territory) {
                    return;
                }

                try {
                    app(BumpService::class)->bump($object, $scope, $actor, 'administrator', $data['comment'] ?? null);
                } catch (BumpRefusedException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('panel.objects.lifecycle.bump_refused'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

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

    private function returnForRevisionAction(): Action
    {
        return Action::make('return_for_revision')
            ->label(__('panel.objects.lifecycle.return_for_revision'))
            ->authorize(fn (Object_ $object): bool => (bool) auth()->user()?->can('update', $object))
            ->schema([
                Select::make('section')
                    ->label(__('panel.objects.lifecycle.section'))
                    ->options(fn (): array => array_combine($this->settingsModeratedTypes(), $this->settingsModeratedTypes()))
                    ->required(),
                Textarea::make('reason')
                    ->label(__('panel.objects.lifecycle.reason'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    $this->service()->returnForRevision($this->currentObject(), $data['section'], $data['reason'], $actor);
                }

                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();
            });
    }

    private function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label(__('panel.objects.lifecycle.duplicate'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('create', Object_::class))
            ->action(function () {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return null;
                }

                $copy = $this->service()->duplicate($this->currentObject(), $actor);

                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();

                return redirect(ObjectResource::getUrl('edit', ['record' => $copy], panel: 'admin'));
            });
    }

    private function transferOwnershipAction(): Action
    {
        return Action::make('transfer_ownership')
            ->label(__('panel.objects.lifecycle.transfer_ownership'))
            ->authorize(fn (Object_ $object): bool => (bool) auth()->user()?->can('update', $object))
            ->schema([
                Select::make('new_owner_id')
                    ->label(__('panel.objects.lifecycle.new_owner'))
                    // Not `->relationship()`: that binding resolves options
                    // and persistence against the record's own relation,
                    // which is the wrong behaviour for an action's own data
                    // field — this select's value is read once in the
                    // action closure and passed to the service explicitly.
                    ->options(fn (): array => User::query()->pluck('name', 'id')->all())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();
                $newOwner = User::query()->find($data['new_owner_id']);

                if ($actor instanceof User && $newOwner instanceof User) {
                    $this->service()->transferOwnership($this->currentObject(), $newOwner, $actor);
                }

                $this->refreshFormData(['owner_id']);
                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();
            });
    }

    /** @return list<string> */
    private function settingsModeratedTypes(): array
    {
        /** @var list<string> $types */
        $types = app(SettingsRepository::class)->get('moderation.moderated_change_types');

        return $types;
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
