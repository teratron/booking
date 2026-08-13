<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Pages;

use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\AvailabilityToggleService;
use App\Services\Cabinet\ObjectDashboardService;
use App\Support\Cabinet\ObjectDashboardSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;

/**
 * The owner cabinet's landing screen for the currently selected object
 * (this panel's Filament tenant) — placement standing, view and click
 * counts, availability, and the entry points an owner reaches for most.
 *
 * Registered at the panel's root path so it is the first thing an owner
 * sees after selecting (or being defaulted to) a tenant object, the same
 * slot {@see \Filament\Pages\Dashboard} claims for the stock panel.
 */
class Dashboard extends Page
{
    /**
     * Each quick action's icon and the Filament route name the screen it
     * points to is expected to register. Object editing now has one — the
     * `ObjectResource` edit page — so `edit_object` names that resource's
     * own generated route rather than the standalone-page name this task
     * anticipated before the resource existed. Bumping, photos, news, and
     * promotions are still built by other tasks; until one of those routes
     * exists, {@see self::quickActions()} resolves it to a disabled action
     * instead of a broken link, and each starts working the moment its own
     * route is registered — no change needed here.
     *
     * @var array<string, array{icon: Heroicon, route: string}>
     */
    private const QUICK_ACTIONS = [
        'edit_object' => ['icon' => Heroicon::OutlinedPencilSquare, 'route' => 'filament.cabinet.resources.objects.edit'],
        'bump_object' => ['icon' => Heroicon::OutlinedBolt, 'route' => 'filament.cabinet.pages.bump-object'],
        'add_photos' => ['icon' => Heroicon::OutlinedPhoto, 'route' => 'filament.cabinet.resources.photos.index'],
        'add_news' => ['icon' => Heroicon::OutlinedNewspaper, 'route' => 'filament.cabinet.resources.news.create'],
        'add_promotion' => ['icon' => Heroicon::OutlinedMegaphone, 'route' => 'filament.cabinet.resources.promotions.create'],
    ];

    protected static string $routePath = '/';

    protected static ?int $navigationSort = -2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected string $view = 'filament.cabinet.pages.dashboard';

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.cabinet.dashboard.title');
    }

    public function getTitle(): string
    {
        return __('panel.cabinet.dashboard.title');
    }

    /**
     * The one-tap availability switch, mounted here so an owner can flip it
     * without leaving the landing screen — reachable without opening the
     * object's edit form at all, and without a confirmation step, since a
     * confirmed tap is the entire point of the switch.
     *
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->availabilityToggleAction(),
        ];
    }

    private function availabilityToggleAction(): Action
    {
        return Action::make('toggle_availability')
            ->label(fn (): string => $this->currentTenantIsAvailable()
                ? __('panel.cabinet.availability.mark_unavailable')
                : __('panel.cabinet.availability.mark_available'))
            ->color(fn (): string => $this->currentTenantIsAvailable() ? 'danger' : 'success')
            ->icon(fn (): string => $this->currentTenantIsAvailable() ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->authorize(fn (): bool => ($tenant = Filament::getTenant()) instanceof Object_
                && (bool) auth()->user()?->can('update', $tenant))
            ->action(function (): void {
                $tenant = Filament::getTenant();
                $actor = Filament::auth()->user();

                if (! $tenant instanceof Object_ || ! $actor instanceof User) {
                    return;
                }

                app(AvailabilityToggleService::class)->toggle($tenant, $actor);

                Notification::make()->title(__('panel.cabinet.availability.toggled'))->success()->send();
            });
    }

    /**
     * Navigation and other panel chrome can render this page before tenant
     * resolution runs, so this must read false rather than throw with no
     * tenant bound.
     */
    private function currentTenantIsAvailable(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Object_ && $tenant->availability_status === 'available';
    }

    /**
     * The current tenant's dashboard read model, or null with no tenant
     * bound — navigation and other panel chrome can render before tenant
     * resolution runs, so this must not throw in that case.
     */
    public function summary(): ?ObjectDashboardSummary
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Object_) {
            return null;
        }

        return app(ObjectDashboardService::class)->summarize($tenant);
    }

    /**
     * @return list<array{key: string, icon: string, url: ?string}>
     */
    public function quickActions(): array
    {
        $tenant = Filament::getTenant();
        // 'record' is only meaningful to `edit_object` (this resource
        // manages the tenant model itself, so tenant and record are the
        // same row) — Laravel's `route()` silently appends an unused
        // parameter as a harmless query string for every other route here,
        // rather than erroring, so one shared parameter set is simpler than
        // branching per action.
        $parameters = $tenant instanceof Object_ ? ['tenant' => $tenant, 'record' => $tenant] : [];

        $actions = [];

        foreach (self::QUICK_ACTIONS as $key => $definition) {
            $actions[] = [
                'key' => $key,
                // Blade Icons resolves a bare "o-…" name against Filament's
                // own small bundled icon set (its rich-editor toolbar
                // glyphs), not the full Heroicons set — that one is
                // registered under the "heroicon" prefix, so the icon
                // component needs the prefixed form to find it.
                'icon' => 'heroicon-'.$definition['icon']->value,
                'url' => Route::has($definition['route']) ? route($definition['route'], $parameters) : null,
            ];
        }

        return $actions;
    }

    /**
     * The route name {@see self::quickActions()} resolves $key's link
     * against — exposed so a test can prove the resolution against that
     * exact name rather than a duplicated literal.
     */
    public static function quickActionRouteName(string $key): ?string
    {
        return self::QUICK_ACTIONS[$key]['route'] ?? null;
    }
}
