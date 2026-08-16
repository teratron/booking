{{--
    Rendered by the framework for any unresolved route (including an
    inactive or invented `{lang}` segment) — never indexable. Title and body
    read the administrator override first, falling back to the portal's own
    static copy when nobody has customized this status code yet.
--}}
@php $override = app(\App\Services\Seo\ErrorPageResolver::class)->resolve(404, app()->getLocale()); @endphp
<x-layouts.public :title="$override['title'] ?? __('public.legal.not_found.title')" :noindex="true">
    <div class="mx-auto max-w-2xl px-4 py-24 text-center">
        <p class="text-sm font-semibold text-brand">404</p>
        <h1 class="mt-2 text-3xl font-semibold text-ink">{{ $override['title'] ?? __('public.legal.not_found.title') }}</h1>
        <p class="mt-4 text-ink-muted">{{ $override['body'] ?? __('public.legal.not_found.body') }}</p>

        <x-public.nav-link
            :href="\Illuminate\Support\Facades\Route::has('public.home') ? route('public.home', ['lang' => app()->getLocale()]) : url('/'.app()->getLocale())"
            class="mt-8 inline-block rounded-lg bg-brand px-6 py-3 text-sm font-medium text-white hover:opacity-90"
        >
            {{ __('public.legal.not_found.home_link') }}
        </x-public.nav-link>
    </div>
</x-layouts.public>
