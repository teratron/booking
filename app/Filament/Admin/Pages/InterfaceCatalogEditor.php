<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Language;
use App\Models\User;
use App\Services\Localization\InterfaceCatalog;
use App\Services\Localization\InterfaceCatalogRepository;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Edits the interface catalog entries the file-backed defaults under
 * `resources/lang` ship. A saved change is rendered on the very next
 * request through `DatabaseOverlayLoader`, and clearing a field back to
 * blank deletes the override, reverting to the shipped default rather than
 * storing an empty string.
 *
 * One field per active language per catalog key; a language with no file of
 * its own (freshly activated) starts every field blank and resolves
 * entirely through the primary-language fallback until filled in.
 *
 * @property-read Schema $form
 */
class InterfaceCatalogEditor extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.interface-catalog-editor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('settings.edit');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.interface_catalog.title');
    }

    public function getTitle(): string
    {
        return __('panel.interface_catalog.title');
    }

    public function mount(): void
    {
        $catalog = app(InterfaceCatalog::class);
        $repository = app(InterfaceCatalogRepository::class);
        $locales = $this->activeLocales();

        $values = [];

        foreach ($catalog->groups() as $group) {
            foreach ($locales as $locale) {
                foreach ($repository->currentValues($locale, $group) as $key => $value) {
                    $values[$this->fieldName($locale, $group, $key)] = $value;
                }
            }
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $catalog = app(InterfaceCatalog::class);
        $locales = $this->activeLocales();

        $tabs = collect($catalog->groups())
            ->flatMap(fn (string $group): Collection => $this->segmentTabs($catalog, $group, $locales))
            ->all();

        return $schema
            ->components([Tabs::make()->tabs($tabs)])
            ->statePath('data');
    }

    public function save(): void
    {
        $repository = app(InterfaceCatalogRepository::class);
        $actor = $this->actor();

        /** @var array<string, mixed> $flatState */
        $flatState = $this->form->getState();

        $grouped = [];

        foreach ($flatState as $fieldName => $value) {
            [$locale, $group, $key] = $this->decodeFieldName((string) $fieldName);
            $grouped[$locale][$group][$key] = (string) $value;
        }

        foreach ($grouped as $locale => $byGroup) {
            foreach ($byGroup as $group => $valuesByKey) {
                // Only the fields an administrator actually touched: the form
                // is pre-filled with every canonical key's current effective
                // value, so writing the raw submitted state unfiltered would
                // turn every untouched, still-shipped key into a stored
                // override on the very first save — permanently shadowing any
                // future edit to that key's shipped file default even though
                // nobody meant to change it.
                $current = $repository->currentValues($locale, $group);
                $changed = array_filter(
                    $valuesByKey,
                    fn (string $value, string $key): bool => ($current[$key] ?? '') !== $value,
                    ARRAY_FILTER_USE_BOTH,
                );

                if ($changed === []) {
                    continue;
                }

                $repository->save($locale, $group, $changed, $actor);
            }
        }

        Notification::make()->title(__('panel.interface_catalog.saved'))->success()->send();
    }

    /**
     * @param  list<string>  $locales
     * @return Collection<int, Tab>
     */
    private function segmentTabs(InterfaceCatalog $catalog, string $group, array $locales): Collection
    {
        return collect($catalog->canonicalKeys($group))
            ->keys()
            ->groupBy(fn (string $key): string => Str::before($key, '.'))
            ->map(fn (Collection $keys, string $segment): Tab => Tab::make(Str::headline($segment))
                ->schema($keys->map(fn (string $key): Fieldset => $this->keyFieldset($group, $key, $locales))->all()))
            ->values();
    }

    /**
     * @param  list<string>  $locales
     */
    private function keyFieldset(string $group, string $key, array $locales): Fieldset
    {
        return Fieldset::make($key)
            ->schema(array_map(
                fn (string $locale): Textarea => Textarea::make($this->fieldName($locale, $group, $key))
                    ->label(strtoupper($locale))
                    ->rows(2),
                $locales,
            ));
    }

    /** @return list<string> */
    private function activeLocales(): array
    {
        return array_values(Language::query()->where('is_active', true)->orderBy('display_order')->pluck('code')->all());
    }

    private function fieldName(string $locale, string $group, string $key): string
    {
        return $locale.'__'.$group.'__'.str_replace('.', '_dot_', $key);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function decodeFieldName(string $fieldName): array
    {
        [$locale, $group, $encodedKey] = explode('__', $fieldName, 3);

        return [$locale, $group, str_replace('_dot_', '.', $encodedKey)];
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
