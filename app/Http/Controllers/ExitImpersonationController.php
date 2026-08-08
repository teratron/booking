<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Owners\ImpersonationService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/**
 * The "return to admin" control a banner shows throughout the cabinet panel
 * while support mode is active. Reachable only by an authenticated session
 * — during impersonation that session is authenticated as the owner, which
 * is exactly the state {@see ImpersonationService::exit()} needs to resolve
 * the impersonated owner from before restoring the administrator.
 */
final class ExitImpersonationController extends Controller
{
    public function __invoke(ImpersonationService $impersonation): RedirectResponse
    {
        $impersonation->exit();

        $adminUrl = Filament::getPanel('admin')->getUrl();

        return redirect()->to(is_string($adminUrl) ? $adminUrl : '/');
    }
}
