<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Cabinet\Pages\Dashboard;
use App\Filament\Cabinet\Pages\Settings;
use App\Models\Object_;
use App\Models\Scopes\ModerationScope;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CabinetPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('cabinet')
            ->path(config('booking.panels.cabinet.path'))
            ->login()
            ->brandName(fn (): string => __('panel.cabinet_brand'))
            ->strictAuthorization()
            ->unsavedChangesAlerts()
            // Settings extends Filament's own EditProfile (password/email
            // change, current-password confirmation, rate limiting) rather
            // than a hand-built page — it opts out of ordinary page
            // discovery by design, so it is registered explicitly here,
            // the same way any profile page must be.
            ->profile(Settings::class, isSimple: false)
            // The tenant is the object being managed, not an
            // organization/team — an owner with several objects switches
            // between them here; the ownership relationship name
            // ('object') is what every tenant-scoped child resource (rooms,
            // prices, services, media) declares on its own model as the
            // BelongsTo back to Object_. No registration page: objects are
            // never self-created through the cabinet, only assigned by
            // staff via the admin panel's Owners resource.
            ->tenant(Object_::class, ownershipRelationship: 'object')
            // Default tenant resolution binds through Object_::query(),
            // which carries the ModerationScope global scope — exactly the
            // scope that hides a draft, rejected, or revision-requested
            // object from public pages. Left unresolved here, an owner whose
            // only object is not yet approved could never reach it: the
            // very state they most need the cabinet to act on. This does
            // not weaken moderation anywhere else — it only widens which of
            // an *authenticated, already-authorized* owner's own objects
            // they may select as their current tenant.
            ->resolveTenantUsing(
                fn (string $key): Object_ => Object_::withoutGlobalScope(ModerationScope::class)->findOrFail($key)
            )
            ->tenantMenu()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Cabinet/Resources'), for: 'App\Filament\Cabinet\Resources')
            ->discoverPages(in: app_path('Filament/Cabinet/Pages'), for: 'App\Filament\Cabinet\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Cabinet/Widgets'), for: 'App\Filament\Cabinet\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Renders nothing at all when support mode is not active — an
            // empty view, not a hidden-but-present one — so the ordinary
            // owner session this panel exists for is never told about a
            // capability that has nothing to do with it.
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.cabinet.impersonation-banner'),
            );
    }
}
