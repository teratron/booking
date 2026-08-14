{{--
    Global search, present in the header on every page. Submits into the
    catalog once a later task registers it; until then it is a harmless
    no-op reload of the current page rather than a dead action.
--}}
@php
    $searchAction = \Illuminate\Support\Facades\Route::has('public.catalog.index')
        ? route('public.catalog.index', ['lang' => app()->getLocale()])
        : url()->current();
@endphp
<form method="GET" action="{{ $searchAction }}" role="search" class="flex w-full items-center gap-2 rounded-lg bg-white px-3 py-2">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
    </svg>
    <input
        type="search"
        name="q"
        value="{{ request('q') }}"
        placeholder="{{ __('public.shell.search.placeholder') }}"
        aria-label="{{ __('public.shell.search.placeholder') }}"
        class="w-full min-w-0 border-none text-sm text-ink placeholder:text-ink-muted focus:outline-none"
    >
</form>
