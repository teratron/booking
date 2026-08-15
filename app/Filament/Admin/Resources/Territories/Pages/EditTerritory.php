<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Territories\Pages;

use App\Exceptions\SlugReuseRefusedException;
use App\Exceptions\TerritoryCycleException;
use App\Filament\Admin\Resources\Territories\TerritoryResource;
use App\Models\Territory;
use App\Models\TerritoryTranslation;
use App\Models\User;
use App\Services\Geography\TerritoryAdministrator;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;

/**
 * Field saves plus reparenting — the one action the geography requirements
 * require a counted confirmation and a cycle guard for, neither of which a
 * plain field save can express.
 */
class EditTerritory extends EditRecord
{
    protected static string $resource = TerritoryResource::class;

    /** @var array<string, array<string, mixed>> */
    private array $pendingTranslations = [];

    /** @return array<string, mixed> */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->currentTerritory();

        /** @var TerritoryTranslation $translation */
        foreach ($record->translations as $translation) {
            $data['translations'][$translation->locale] = $translation->only([
                'name', 'slug', 'short_description', 'seo_title',
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
        /** @var Territory $record */
        $record->update($data);

        $touchedSlug = false;

        foreach ($this->pendingTranslations as $locale => $fields) {
            $fields = array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');

            if ($fields === []) {
                continue;
            }

            if (isset($fields['slug'])) {
                $touchedSlug = true;
            }

            $fields['slug'] ??= $record->translations()->where('locale', $locale)->value('slug')
                ?? Str::slug($fields['name'] ?? (string) $record->id);

            $record->translations()->updateOrCreate(['locale' => $locale], $fields);
        }

        if ($touchedSlug) {
            /** @var Territory $reloaded */
            $reloaded = $record->fresh();

            try {
                app(TerritoryAdministrator::class)->recomputeSlugPaths($reloaded);
            } catch (SlugReuseRefusedException $exception) {
                Notification::make()
                    ->danger()
                    ->title(__('panel.territories.form.slug_claimed_by_redirect'))
                    ->body($exception->getMessage())
                    ->send();
            }
        }

        return $record;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->reparentAction(),
            DeleteAction::make(),
        ];
    }

    private function reparentAction(): Action
    {
        return Action::make('reparent')
            ->label(__('panel.territories.actions.reparent'))
            ->authorize(fn (Territory $territory): bool => (bool) auth()->user()?->can('update', $territory))
            ->schema([
                Select::make('new_parent_id')
                    ->label(__('panel.territories.actions.new_parent'))
                    ->options(fn (): array => Territory::query()
                        ->where('id', '!=', $this->currentTerritory()->id)
                        ->with('translations')
                        ->get()
                        ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
                        ->all())
                    ->placeholder(__('panel.territories.actions.no_parent'))
                    ->searchable(),
            ])
            ->requiresConfirmation()
            ->modalDescription(fn (): string => __('panel.territories.actions.reparent_confirm', app(TerritoryAdministrator::class)->blastRadius($this->currentTerritory())))
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    app(TerritoryAdministrator::class)->reparent(
                        $this->currentTerritory(),
                        isset($data['new_parent_id']) ? (int) $data['new_parent_id'] : null,
                        $actor,
                    );
                } catch (TerritoryCycleException $exception) {
                    Notification::make()->title(__('panel.territories.actions.cycle_refused'))->body($exception->getMessage())->danger()->send();

                    return;
                }

                $this->refreshFormData(['country_id']);
                Notification::make()->title(__('panel.objects.lifecycle.applied'))->success()->send();
            });
    }

    private function currentTerritory(): Territory
    {
        /** @var Territory $record */
        $record = $this->getRecord();

        return $record;
    }
}
