<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Admin\Auth\Login;
use App\Http\Middleware\EnsureSecondFactorForPrivilegedRoles;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('booking.panels.admin.path'))
            ->login(Login::class)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsureSecondFactorForPrivilegedRoles::class)
            ->brandName(fn (): string => __('panel.brand'))
            // A resource with no policy, or a policy missing the method being
            // checked, is permitted by default and silently opens the whole
            // resource. Strict mode turns that into a LogicException at the
            // first request, which is the only way the omission gets noticed
            // before an administrator finds the section they should not have.
            ->strictAuthorization()
            // Guards navigation away from a dirty form. Panel-wide, because a
            // per-resource opt-in is a per-resource omission.
            ->unsavedChangesAlerts()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups($this->navigationGroups())
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
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
            ]);
    }

    /**
     * The panel's section structure, in the order the client's delivery
     * priority names. A group renders only when the actor can see at least one
     * of its resources, which the toolkit derives from each resource's policy —
     * so this list is a layout declaration, never an access decision. Groups
     * whose resources arrive in a later phase are absent rather than empty: a
     * heading with nothing beneath it reads as a permission failure.
     *
     * @return list<NavigationGroup>
     */
    private function navigationGroups(): array
    {
        return [
            NavigationGroup::make()->label(fn (): string => __('panel.navigation.catalog')),
            NavigationGroup::make()->label(fn (): string => __('panel.navigation.geography')),
            NavigationGroup::make()->label(fn (): string => __('panel.navigation.governance')),
            NavigationGroup::make()->label(fn (): string => __('panel.navigation.access')),
            NavigationGroup::make()->label(fn (): string => __('panel.navigation.system')),
        ];
    }
}
