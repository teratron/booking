<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\Integrations\MapTileConfigResolver;
use App\Services\Seo\SeoHealthReport;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The six checks {@see SeoHealthReport} computes, rendered as a single
 * screen so an SEO specialist can find a duplicate canonical URL or a
 * title over the search-result truncation length without browsing the
 * catalog row by row.
 */
class SeoHealthDashboard extends Page
{
    protected string $view = 'filament.admin.pages.seo-health-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'seo';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('seo.view');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.seo_health.navigation_label');
    }

    public function getTitle(): string
    {
        return __('panel.seo_health.title');
    }

    /** @return array<string, list<array{entityType: string, locale: string, name: string}>> */
    public function warnings(): array
    {
        return app(SeoHealthReport::class)->warnings();
    }

    /**
     * Portal-wide, not entity-scoped — deliberately not one of
     * {@see SeoHealthReport::warnings()}'s own rows, which are each about
     * one specific catalog row's own SEO data. This is the map component's
     * own "no provider key configured" condition (F-16), surfaced here
     * since it is the closest thing this project has to a general
     * third-party-integration health screen.
     */
    public function mapTileKeyMissing(): bool
    {
        return ! app(MapTileConfigResolver::class)->hasKey();
    }

    public function entityTypeLabel(string $entityType): string
    {
        return __("panel.seo_metadata_templates.form.entity_types.{$entityType}");
    }
}
