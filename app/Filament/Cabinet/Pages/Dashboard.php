<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Pages;

use App\Models\Object_;
use App\Services\Cabinet\ObjectDashboardService;
use App\Support\Cabinet\ObjectDashboardSummary;
use BackedEnum;
use Filament\Facades\Filament;
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
     * points to is expected to register. The rest of the owner cabinet
     * (object editing, bumping, photos, news, promotions) is built by other
     * tasks in this project; until one of those routes exists,
     * {@see self::quickActions()} resolves it to a disabled action instead
     * of a broken link, and it starts working the moment that route is
     * registered — no change needed here.
     *
     * @var array<string, array{icon: Heroicon, route: string}>
     */
    private const QUICK_ACTIONS = [
        'edit_object' => ['icon' => Heroicon::OutlinedPencilSquare, 'route' => 'filament.cabinet.pages.edit-object'],
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
        $parameters = $tenant instanceof Object_ ? ['tenant' => $tenant] : [];

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
