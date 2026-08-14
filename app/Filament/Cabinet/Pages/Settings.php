<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Pages;

use App\Models\Language;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SensitiveParameter;

/**
 * The owner cabinet's account settings — everything {@see EditProfile}
 * already provides (name, email, password, all with the base class's own
 * current-password confirmation and rate limiting) plus two additions this
 * portal's owner cabinet specifically needs: the account's own interface
 * language, and per-type notification delivery preferences.
 *
 * Notification preferences are not `User` columns — they live in
 * `notification_preferences`, one row per (user, type) — so they are
 * excluded from the base class's own `$record->update($data)` call and
 * persisted separately through {@see NotificationPreferenceService}, the
 * same service the notification dispatch pipeline itself reads.
 */
class Settings extends EditProfile
{
    /** @var array<string, bool> */
    private array $pendingPreferences = [];

    /**
     * The URL path segment only (`/settings` rather than the base class's
     * own `/profile`) — the route NAME must stay exactly `auth.profile`,
     * unchanged from the base class. `Panel::getProfileUrl()` (the layout's
     * own "my profile" link, rendered on every page in this panel) resolves
     * that name directly rather than through this page's own
     * `getRelativeRouteName()`, so overriding the route name here would
     * leave every other page's layout looking up a route that no longer
     * exists.
     */
    public static function getSlug(?Panel $panel = null): string
    {
        return 'settings';
    }

    public static function getLabel(): string
    {
        return __('panel.cabinet.settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('panel.cabinet.settings.title');
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        $user = $this->getUser();

        if (! $user instanceof User) {
            return;
        }

        $preferences = app(NotificationPreferenceService::class);

        $this->data['notification_preferences'] = $this->optionalNotificationTypes()
            ->mapWithKeys(fn (NotificationType $type): array => [
                (string) $type->id => $preferences->isEnabled($user, $type),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        $this->pendingPreferences = $data['notification_preferences'] ?? [];
        unset($data['notification_preferences']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);

        if ($record instanceof User) {
            $this->saveNotificationPreferences($record);
        }

        return $record;
    }

    private function saveNotificationPreferences(User $user): void
    {
        $service = app(NotificationPreferenceService::class);

        foreach ($this->optionalNotificationTypes() as $type) {
            $key = (string) $type->id;
            $enabled = array_key_exists($key, $this->pendingPreferences) ? $this->pendingPreferences[$key] : true;
            $service->setEnabled($user, $type, $enabled);
        }
    }

    /** @return Collection<int, NotificationType> */
    private function optionalNotificationTypes(): Collection
    {
        return NotificationType::query()
            ->where('class', 'optional')
            ->where('is_active', true)
            ->get();
    }

    protected function getLocaleFormComponent(): Component
    {
        return Select::make('locale')
            ->label(__('panel.cabinet.settings.locale'))
            ->options(fn (): array => Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->pluck('short_label', 'code')
                ->all())
            ->native(false)
            ->placeholder(__('panel.cabinet.settings.locale_placeholder'));
    }

    protected function getNotificationPreferencesFormComponent(): Component
    {
        return Section::make(__('panel.cabinet.settings.notification_preferences_title'))
            ->description(__('panel.cabinet.settings.notification_preferences_description'))
            ->components(fn (): array => $this->optionalNotificationTypes()
                ->map(fn (NotificationType $type): Component => Toggle::make("notification_preferences.{$type->id}")
                    ->label(__('panel.cabinet.settings.notification_types.'.$type->key)))
                ->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getLocaleFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                $this->getNotificationPreferencesFormComponent(),
            ]);
    }
}
