<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/**
 * Renders the portal's informational static pages — About and Contacts —
 * the two the shell footer has linked since it was built. Both are
 * reachable without authentication and, like the legal pages, source their
 * copy from the same translation-file mechanism rather than a database
 * table: this is portal-wide, developer-maintained text, not per-entity
 * content an administrator authors.
 */
final class StaticPageController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function about(): View
    {
        return view('public.static.about', ['breadcrumbs' => $this->breadcrumbs(__('public.static.about.title'))]);
    }

    public function contacts(): View
    {
        return view('public.static.contacts', [
            'breadcrumbs' => $this->breadcrumbs(__('public.static.contacts.title')),
            'contactEmail' => $this->settings->get('portal.contact_email'),
            'contactPhone' => $this->settings->get('portal.contact_phone'),
        ]);
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
