<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Exceptions\CriticalSettingException;
use App\Models\User;
use App\Services\Settings\SettingDefinition;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsRepository;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The screen behind "changeable without a programmer".
 *
 * It renders whatever the settings registry declares and nothing else — adding
 * a parameter means declaring it, not editing this class. Critical settings
 * render disabled for accounts that may not write them, which is a courtesy;
 * the refusal itself happens in the repository, where an import or a console
 * command reaches the same writer by a path no screen guards.
 *
 * @property-read Schema $form
 */
class PortalSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.portal-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('settings_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.settings.title');
    }

    public function getTitle(): string
    {
        return __('panel.settings.title');
    }

    public function mount(): void
    {
        $this->form->fill(app(SettingsRepository::class)->all());
    }

    public function form(Schema $schema): Schema
    {
        $registry = app(SettingsRegistry::class);

        return $schema
            ->components([
                Tabs::make()->tabs(array_map(
                    fn (string $group): Tab => Tab::make(__("panel.settings.groups.{$group}"))
                        ->schema(array_map(
                            fn (SettingDefinition $definition) => $this->field($definition),
                            $registry->inGroup($group),
                        )),
                    $registry->groups(),
                )),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            app(SettingsRepository::class)->setMany($this->form->getState());
        } catch (CriticalSettingException $exception) {
            Notification::make()
                ->title(__('panel.settings.critical_refused'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title(__('panel.settings.saved'))->success()->send();
    }

    private function field(SettingDefinition $definition): TextInput|TagsInput|Toggle
    {
        $label = __("panel.settings.fields.{$definition->key}");
        $disabled = $definition->isCritical && ! $this->mayWriteCritical();

        return match ($definition->type) {
            'bool' => Toggle::make($definition->key)->label($label)->disabled($disabled),
            'array' => TagsInput::make($definition->key)->label($label)->disabled($disabled),
            'int' => TextInput::make($definition->key)->label($label)->numeric()->disabled($disabled),
            default => TextInput::make($definition->key)->label($label)->disabled($disabled),
        };
    }

    private function mayWriteCritical(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasRole('chief_administrator');
    }
}
