<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Language;
use App\Models\User;
use App\Services\Localization\InterfaceCatalog;
use App\Services\Localization\InterfaceCatalogRepository;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
 * The catalog holds ~1,400 keys across two groups; one Textarea per key per
 * active language is ~2,800 fields, which rendered together is an ~11 MB
 * page. The editor shows one (group, section) slice at a time — two
 * `->live()` Selects at the top pick it, and only that slice's fields are
 * built and filled. Both Selects and the slice fields share the form's own
 * `data` state path, so the picker is its own single source of truth.
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
        $group = app(InterfaceCatalog::class)->groups()[0] ?? null;
        $segment = $this->segmentsFor($group)[0] ?? null;

        $this->form->fill($this->sliceState($group, $segment));
    }

    public function form(Schema $schema): Schema
    {
        $catalog = app(InterfaceCatalog::class);
        $locales = $this->activeLocales();

        $group = $this->currentGroup();
        $segment = $this->currentSegment();

        $groupOptions = array_combine($catalog->groups(), array_map(Str::headline(...), $catalog->groups()));
        $segments = $this->segmentsFor($group);
        $segmentOptions = array_combine($segments, array_map(Str::headline(...), $segments));

        $keyFieldsets = $group !== null && $segment !== null
            ? array_map(
                fn (string $key): Fieldset => $this->keyFieldset($group, $key, $locales),
                $this->segmentKeys($group, $segment),
            )
            : [];

        return $schema
            ->components([
                Fieldset::make(__('panel.interface_catalog.section_picker'))
                    ->columns(2)
                    ->schema([
                        Select::make('selectedGroup')
                            ->label(__('panel.interface_catalog.group'))
                            ->options($groupOptions)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state): void {
                                $this->form->fill($this->sliceState($state, $this->segmentsFor($state)[0] ?? null));
                            }),
                        Select::make('selectedSegment')
                            ->label(__('panel.interface_catalog.section'))
                            ->options($segmentOptions)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state): void {
                                $this->form->fill($this->sliceState($this->currentGroup(), $state));
                            }),
                    ]),
                ...$keyFieldsets,
            ])
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
            // The slice-picker Selects live in the same state path; they
            // carry no `__` separator, so they are skipped here.
            if (! str_contains((string) $fieldName, '__')) {
                continue;
            }

            [$locale, $group, $key] = $this->decodeFieldName((string) $fieldName);
            $grouped[$locale][$group][$key] = (string) $value;
        }

        foreach ($grouped as $locale => $byGroup) {
            foreach ($byGroup as $group => $valuesByKey) {
                // Only the fields an administrator actually touched: the form
                // is pre-filled with the current effective value of every key
                // in the shown slice, so writing the raw submitted state
                // unfiltered would turn every untouched, still-shipped key
                // into a stored override, permanently shadowing any future
                // edit to that key's shipped file default.
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
     * The full form state for one (group, section) slice: the two picker
     * values plus one field per active locale per key in that section.
     *
     * @return array<string, mixed>
     */
    private function sliceState(?string $group, ?string $segment): array
    {
        $state = ['selectedGroup' => $group, 'selectedSegment' => $segment];

        if ($group === null || $segment === null) {
            return $state;
        }

        $repository = app(InterfaceCatalogRepository::class);

        foreach ($this->activeLocales() as $locale) {
            $current = $repository->currentValues($locale, $group);

            foreach ($this->segmentKeys($group, $segment) as $key) {
                $state[$this->fieldName($locale, $group, $key)] = $current[$key] ?? '';
            }
        }

        return $state;
    }

    private function currentGroup(): ?string
    {
        $group = $this->data['selectedGroup'] ?? null;
        $groups = app(InterfaceCatalog::class)->groups();

        return is_string($group) && in_array($group, $groups, true) ? $group : ($groups[0] ?? null);
    }

    private function currentSegment(): ?string
    {
        $segments = $this->segmentsFor($this->currentGroup());
        $segment = $this->data['selectedSegment'] ?? null;

        return is_string($segment) && in_array($segment, $segments, true) ? $segment : ($segments[0] ?? null);
    }

    /**
     * The distinct first path components of a group's canonical keys, in
     * declaration order — `navigation.catalog` and `navigation.commerce`
     * both fall under the `navigation` section.
     *
     * @return list<string>
     */
    private function segmentsFor(?string $group): array
    {
        if ($group === null) {
            return [];
        }

        $segments = [];

        foreach (array_keys(app(InterfaceCatalog::class)->canonicalKeys($group)) as $key) {
            $segments[Str::before($key, '.')] = true;
        }

        return array_keys($segments);
    }

    /**
     * Every canonical key of $group whose section is $segment.
     *
     * @return list<string>
     */
    private function segmentKeys(string $group, string $segment): array
    {
        return array_values(array_filter(
            array_keys(app(InterfaceCatalog::class)->canonicalKeys($group)),
            fn (string $key): bool => Str::before($key, '.') === $segment,
        ));
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
