<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/**
 * Renders the portal's static legal pages — privacy policy and terms of
 * use. Both are reachable without authentication and linked persistently
 * from the shell footer.
 */
final class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return view('public.legal.privacy', ['breadcrumbs' => $this->breadcrumbs(__('public.legal.privacy.title'))]);
    }

    public function terms(): View
    {
        return view('public.legal.terms', ['breadcrumbs' => $this->breadcrumbs(__('public.legal.terms.title'))]);
    }

    /** @return list<array{label: string, url: string}> */
    private function breadcrumbs(string $currentLabel): array
    {
        $homeUrl = Route::has('public.home')
            ? route('public.home', ['lang' => app()->getLocale()])
            : url('/'.app()->getLocale());

        return [
            ['label' => __('public.shell.breadcrumbs.home'), 'url' => $homeUrl],
            ['label' => $currentLabel, 'url' => url()->current()],
        ];
    }
}
